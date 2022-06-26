<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\WebLK;
	use Me\Korolevsky\BonchBot\OpenSSL;
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
				$vkApi->sendMessage("🔐 У Вас уже привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Профиль","payload":"{ \"command\": \"eval\", \"cmd\": \"/profile\" }"},"color":"primary"}],[{"action":{"type":"text","label":"Отвязать","payload":"{ \"command\": \"eval\", \"cmd\": \"/unbind\" }"},"color":"negative"}]],"inline":true}'
				]);
				$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);
				return true;
			}

			$bonch_group_id = Data::GROUP_ID;
			if($msg[1] == null) {
				$api->commandNeedArguments("ℹ️ Ответьте на данное сообщение, указав [club$bonch_group_id|логин] от личного кабинета.", [ 'command' => '/bind' ]);
				$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);

				return false;
			} elseif($msg[2] == null) {
				$api->commandNeedArguments("ℹ️ Ответьте на данное сообщение, указав [club$bonch_group_id|пароль] от личного кабинета.", [ 'command' => $object['text'] ]);
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
			$conversation_message_id = $vkApi->sendMessage("🔐 Пробуем авторизовать аккаунт.", [
					'peer_ids' => $object['peer_id'],
					'forward' => $forward
				]
			)[0]['conversation_message_id'];
			$vkApi->get('messages.delete', [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]);

			$webLK = new WebLK(0, $login, $password);
			$attempt = $webLK->getInfo();

			if(!$attempt) {
				$vkApi->editMessage("🚫 Авторизоваться в ЛК не удалось, проверьте данные и попробуйте авторизоваться с ними на сайте.", $conversation_message_id, $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://lk.sut.ru/","label":"Сайт ЛК","payload":""}}]],"inline":true}'
				]);
				return false;
			}

			$group_id = R::findOne('groups', 'WHERE `name` LIKE ?', [ "%${attempt['group']}%" ])['id'];
			if($group_id == null) {
				$vkApi->editMessage("🚫 Не удалось определить Вашу группу, попробуйте чуть позже.", $conversation_message_id, $object['peer_id']);
				return false;
			}

			$users = R::getAll('SELECT * FROM `users` WHERE `group_id` = ?', [ $group_id ]);
			foreach($users as $user) {
				$user_login = OpenSSL::decrypt($user['login']);
				if($login == $user_login) {
					$vkApi->editMessage("🚫 Данный аккаунт ЛК уже привязан к другому пользователю!", $conversation_message_id, $object['peer_id']);
					return false;
				}
			}
			$vkApi->editMessage("🔨 Данные успешно проверены, синхронизируем их.", $conversation_message_id, $object['peer_id']);

			$cookie = $attempt['cookie'];
			unset($attempt['cookie']);

			$db = R::dispense('users');
			$db['user_id'] = $object['from_id'];
			$db['group_id'] = $group_id;
			$db['time'] = time();
			$db['cookie'] = OpenSSL::encrypt($cookie);
			$db['login'] = OpenSSL::encrypt($login);
			$db['password'] = OpenSSL::encrypt($password);
			$db['data'] = json_encode($attempt);
			$db['settings'] = json_encode(['type_marking' => 0, 'send_notifications' => 1, 'mailing' => 1, 'new_messages' => 1, 'schedule_from_lk' => 1, 'marks_notify' => 1]);
			R::store($db);

			// Прочитаем все новые сообщения из ЛК, чтобы бот не проспамил об этом после привязки.
			$lk = new LK($object['from_id']);
			$lk->auth();
			$lk->getNewMessages();
			$lk->getNewFilesGroup();

			$vkApi->editMessage("☺️ Большое спасибо за доверие и регистрацию в нашем боте.\n\n⚡️ Все данные были успешно проверены и синхронизированы на нашей стороне, теперь вам доступно большинство функций бота.\n\nℹ️ Узнать их вы можете в инструкции: https://vk.com/@botbonch-about-me", $conversation_message_id, $object['peer_id'], [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}],[{"action":{"type":"text","label":"Заказ справок 📑","payload":"{ \"command\": \"eval\", \"cmd\": \"/order\" }"},"color":"secondary"},{"action":{"type":"text","label":"Профиль 🙇🏻","payload":"{ \"command\": \"eval\", \"cmd\": \"/profile\" }"},"color":"secondary"}],[{"action":{"type":"text","label":"Отметки 🔖","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"},{"action":{"type":"text","label":"Сообщения 📪","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages\" }"},"color":"positive"},{"action":{"type":"text","label":"Оценки 📚","payload":"{ \"command\": \"eval\", \"cmd\": \"/marks\" }"},"color":"negative"}]]}'
			]);
			return true;
		}

	}