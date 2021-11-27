<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;
	use RedBeanPHP\R;

	class Info implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$payload = (array) $object['payload'];

			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			$name = $vkApi->getName($object['from_id']);
			$lk = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);

			$text = "🙎🏻‍♂️ $name:\n\n🧡️ ЛК: " . ($lk != null ? "привязан" : "не привязан") . ".\n";
			if($lk != null) {
				$data = json_decode($lk['data'], true);
				$text .= "🛂 ФИО: ${data['name']}\n🎂 Дата рождения: ${data['birthday']}\n📘 Группа: ${data['group']}";
			}

			if($payload['update'] != null) {
				$vkApi->editMessage($text, $payload['update'], $object['peer_id'], [
					'keyboard' => $lk != null ? '{"buttons":[[{"action":{"type":"callback","label":"Настройки","payload":"{ \"command\": \"settings\", \"for\": '.$object['from_id'].' }"},"color":"negative"}]],"inline":true}' : ''
				]);
			} else {
				$vkApi->sendMessage($text, [
					'keyboard' => $lk != null ? '{"buttons":[[{"action":{"type":"callback","label":"Настройки","payload":"{ \"command\": \"settings\", \"for\": '.$object['from_id'].' }"},"color":"negative"}]],"inline":true}' : ''
				]);
			}
			return true;
		}

	}