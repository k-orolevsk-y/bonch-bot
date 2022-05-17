<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use Me\Korolevsky\BonchBot\LK;
	use RedBeanPHP\R;

	class SetSettings implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if(!in_array($payload['key'], ['type_marking', 'send_notifications', 'mailing', 'new_messages', 'schedule_from_lk', 'marks_notify']) || !in_array($payload['value'], [0, 1])) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Данные повреждены."])
				]);
				return false;
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [$object['user_id']]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных." ])
				]);
				return false;
			}

			if($payload['key'] == 'new_messages' && $payload['value']) {
				$lk = new LK($object['user_id']);
				if(!$lk->auth()) {
					$vkApi->get("messages.sendMessageEventAnswer", [
						'peer_id' => $object['peer_id'],
						'user_id' => $object['user_id'],
						'event_id' => $object['event_id'],
						'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "⚠️ Не удалось авторизоваться в ЛК и синхронизировать данные, для установки данной настройки." ])
					]);
					return false;
				}

				$lk->getNewMessages();
				$lk->getNewFilesGroup();
				// Объяснение данному коду есть в Bind.php...
			}

			$settings = json_decode($user['settings'], true);
			$settings[$payload['key']] = intval($payload['value']);
			$user['settings'] = json_encode($settings);
			R::store($user);

			return new Settings($api, $object, $payload);
		}

	}