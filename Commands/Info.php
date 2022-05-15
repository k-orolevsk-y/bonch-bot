<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

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

			$keyboard = $lk != null ? '{"buttons":[[{"action":{"type":"callback","label":"Настройки","payload":"{ \"command\": \"settings\", \"for\": '.$object['from_id'].' }"},"color":"negative"}]],"inline":true}' : '';
			if($payload['update'] != null) {
				$vkApi->editMessage($text, $payload['update'], $object['peer_id'], [
					'keyboard' => $keyboard
				]);
			} else {
				$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
				$payload['update'] = $vkApi->sendMessage($text, [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward,
						'keyboard' => $keyboard
					]
				)[0]['conversation_message_id'];
			}

			// Получаем данные об оценках...
			if($lk != null) {
				$lk = new LK($object['from_id']);
				if($lk->auth() != 1 || ($marks = $lk->getOnlyMarks()) == null) {
					return true; // Не авторизовался или нет оценок - инфы не будет
				}

				$text .= "\n\n🚔 Количество пропусков: ${marks['pass']}\n☀️ Количество оценок (5/4/3/2): ${marks['well']}/${marks['good']}/${marks['not_bad']}/${marks['bad']}\n\nℹ️ Данные актуальны на данный семестр.";
				$vkApi->editMessage($text, $payload['update'], $object['peer_id'], [
					'keyboard' => $keyboard
				]);
			}
			return true;
		}

	}