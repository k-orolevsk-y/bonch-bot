<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class GroupMembers implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			$user = R::findOne('users', 'WHERE `user_id` = ?', [$object['user_id']]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных."])
				]);
				return false;
			}

			$lk = new LK($object['user_id']);
			if(!$lk->auth()) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => '⚠️ Авторизоваться в ЛК не удалось, попробуйте ещё раз.' ])
				]);
				return false;
			}

			$users = R::getAll('SELECT * FROM `users` WHERE `group_id` = ?', [ $user['group_id'] ]);
			$group_members = $lk->getGroupMembers();

			foreach($users as $user_group) {
				$data = json_decode($user_group['data'], true);
				if(in_array($data['name'], $group_members['members'])) {
					$key = array_search($data['name'], $group_members['members']);

					$group_members['members'][$key] = "[id${user_group['user_id']}|" . $group_members['members'][$key] . ']';
				}
			}

			$text = "👨🏻‍🎓 ${group_members['title']}:\n\n";
			foreach($group_members['members'] as $key => $member) {
				$text .= ($key+1).". ".$member."\n";
			}
			$text .= "\n\nℹ️ Ссылки на профиль ВКонтакте получены на основе регистрации в боте.";

			$vkApi->editMessage($text, $object['conversation_message_id'], $object['peer_id'], [
				'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Назад","payload":"{ \"command\": \"eval\", \"cmd\": \"/info\", \"update\": '.$object['conversation_message_id'].' }"},"color":"negative"}]],"inline":true}'
			]);
			return true;
		}

	}