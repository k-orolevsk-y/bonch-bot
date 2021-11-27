<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Commands\Marking;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class DelMark implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if($payload['update'] == null || $payload['update'] == 0) {
				$payload['update'] = $object['conversation_message_id'];
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['user_id'] ]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных." ])
				]);
				return false;
			}
			$type = json_decode($user['settings'], true)['type_marking'] == 0 ? "carousel" : "keyboard";

			$db = R::findOne('schedule', 'WHERE `id` = ? AND `user_id` = ?', [ $payload['mark_id'], $object['user_id'] ]);
			$data = json_decode(R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [ $object['user_id'], 'schedule-'.$payload['date'] ])['data'], true);

			if($db == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🤔 Задача уже была удалена." ])
				]);
				$vkApi->editMessage("⚙️ Выберите пары на которых хотите отметиться:", $payload['update'], $object['peer_id'], Marking::getKeyboardOrCarousel($type, $data, $object, $payload['update'], $payload['date']));
				return false;
			} elseif($db['status'] == 1000) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🚫 Удалить задачу невозможно, поскольку уже была поставлена отметка." ])
				]);
				$vkApi->editMessage("⚙️ Выберите пары на которых хотите отметиться:", $payload['update'], $object['peer_id'], Marking::getKeyboardOrCarousel($type, $data, $object, $payload['update'], $payload['date']));
				return false;
			}


			R::trash($db);
			$vkApi->get("messages.sendMessageEventAnswer", [
				'peer_id' => $object['peer_id'],
				'user_id' => $object['user_id'],
				'event_id' => $object['event_id'],
				'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "❎ Задача на установку отметки была удалена." ])
			]);
			$vkApi->editMessage("⚙️ Выберите пары на которых хотите отметиться:", $payload['update'], $object['peer_id'], Marking::getKeyboardOrCarousel($type, $data, $object, $payload['update'], $payload['date']));
			return true;
		}
	}