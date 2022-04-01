<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\WebLK;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Bind implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', $object['text']);

			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("❗️ Команда не работает в беседах.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);
				return false;
			}

			$db = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($db != null) {
				$vkApi->sendMessage("📛 У Вас уже привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Отвязать","payload":"{ \"command\": \"eval\", \"cmd\": \"/unbind\" }"},"color":"negative"}]],"inline":true}'
				]);
				$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);
				return true;
			}

			if($msg[2] == null) {
				$vkApi->sendMessage("ℹ️ Правильное использование: /bind [логин] [пароль]");
				$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);

				return false;
			}

			$login = $msg[1];
			$password = $msg[2];

			if(preg_match('/\[.*\]/', $login) != 0 || preg_match('/\[.*\]/', $password) != 0) {
				$vkApi->sendMessage("⚠️ Логин и пароль не нужно писать в квадратных скобках!\n💡 В подсказках они указаны, как аргумент.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/@botbonch-about-me","label":"Подробная инструкция","payload":""}}]],"inline":true}'
				]);
				$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);

				return false;
			}

			$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
			$conversation_message_id = $vkApi->sendMessage("📡 Попытка авторизации...", [
					'peer_ids' => $object['peer_id'],
					'forward' => $forward
				]
			)[0]['conversation_message_id'];
			$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);

			$webLK = new WebLK(0, $login, $password);
			$attempt = $webLK->getInfo();

			if(!$attempt) {
				$vkApi->editMessage("❌ Авторизоваться в ЛК не удалось, скорее всего данные неверны.", $conversation_message_id, $object['peer_id']);
				return false;
			}

			$group_id = R::findOne('groups', 'WHERE `name` LIKE ?', [ "%${attempt['group']}%" ])['id'];
			if($group_id == null) {
				$vkApi->editMessage("❌ Не удалось определить Вашу группу, попробуйте чуть позже.", $conversation_message_id, $object['peer_id']);
				return false;
			}

			$cookie = $attempt['cookie'];
			unset($attempt['cookie']);

			$db = R::dispense('users');
			$db['user_id'] = $object['from_id'];
			$db['group_id'] = $group_id;
			$db['time'] = time();
			$db['cookie'] = bin2hex(openssl_encrypt($cookie, 'AES-128-CBC', Data::ENCRYPT_KEY));
			$db['login'] = bin2hex(openssl_encrypt($login, 'AES-128-CBC', Data::ENCRYPT_KEY));
			$db['password'] = bin2hex(openssl_encrypt($password, 'AES-128-CBC', Data::ENCRYPT_KEY));
			$db['data'] = json_encode($attempt);
			$db['settings'] = json_encode(['type_marking' => 0, 'send_notifications' => 1, 'mailing' => 1, 'new_messages' => 1, 'schedule_from_lk' => 1]);
			R::store($db);

			// Прочитаем все новые сообщения из ЛК, чтобы бот не проспамил об этом после привязки.
			$lk = new LK($object['from_id']);
			$lk->auth();
			$lk->getNewMessages();

			$vkApi->editMessage("✅ Авторизация в ЛК прошла успешно, данные записаны.", $conversation_message_id, $object['peer_id'], [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}],[{"action":{"type":"text","label":"Заказ справок 📑","payload":"{ \"command\": \"eval\", \"cmd\": \"/order\" }"},"color":"secondary"},{"action":{"type":"text","label":"Профиль 🙇🏻","payload":"{ \"command\": \"eval\", \"cmd\": \"/profile\" }"},"color":"secondary"}],[{"action":{"type":"text","label":"Отметки 🔖","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"},{"action":{"type":"text","label":"Сообщения 📪","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages\" }"},"color":"positive"},{"action":{"type":"text","label":"Оценки 📚","payload":"{ \"command\": \"eval\", \"cmd\": \"/marks\" }"},"color":"negative"}]]}'
			]);
			return true;
		}

	}