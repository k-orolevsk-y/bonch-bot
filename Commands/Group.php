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
					$vkApi->sendMessage("📛 У Вас привязан ЛК, группу привязывать не нужно.");
					return true;
				}
			}

			$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
			if($bind != null) {
				R::trash($bind);
				$vkApi->sendMessage("❗️ Группа была отвязана.");

				return true;
			}

			if($msg[1] == null) {
				$command = str_replace(['!', '/'], '', mb_strtolower($msg[0]));
				$vkApi->sendMessage("ℹ️ Правильное использование: /${command} [группа]");

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

			$vkApi->sendMessage("✅ Группа ${group['name']} была успешно привязана.");
			return true;
		}

	}