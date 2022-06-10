<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Group implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', $object['text']);

			if($object['peer_id'] <= 2000000000) {
				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
				if($user != null) {
					$vkApi->sendMessage("🚫 У Вас привязан ЛК, группу привязывать не нужно.");
					return true;
				}
			}

			$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
			if($bind != null) {
				R::trash($bind);
				$vkApi->sendMessage("❗️ Группа была отвязана.", [ 'keyboard' => '{"buttons":[]}' ]);

				return true;
			}

			if($msg[1] == null) {
				$api->commandNeedArguments('ℹ️ Ответьте на данное сообщение, указав номер группы.', [ 'command' => '/group' ]);
				return false;
			}

			if(preg_match('/\[.*\]/', $msg[1]) != 0) {
				$vkApi->sendMessage("⚠️ Номер группы не нужно писать в квадратных скобках!\n💡 В подсказках он указан, как аргумент.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/@botbonch-about-me","label":"Подробная инструкция","payload":""}}]],"inline":true}'
				]);
				return false;
			}

			$group = R::findOne('groups', 'WHERE `name` LIKE ?', [ "%${msg[1]}%" ]);
			if($group == null) {
				$vkApi->sendMessage("🚫 Такая группа не найдена. Проверьте название группы на опечатки.");
				return false;
			}

			$bind = R::getRedBean()->dispense('chats_bind');
			$bind['peer_id'] = $object['peer_id'];
			$bind['group_id'] = $group['id'];
			R::store($bind);

			$vkApi->sendMessage("✅ Группа «${group['name']}» была успешно привязана.". ($object['peer_id'] <= 2000000000 ? "\nℹ️ Привязка группы не даёт доступ к основным командам, она даёт доступ исключительно к команде: /расписание." : ""), [ 'keyboard' => $object['peer_id'] <= 2000000000 ? '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}]]}' : '' ]);
			return true;
		}

	}