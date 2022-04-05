<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Commands\Marking;
	use Me\Korolevsky\BonchBot\LK;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class SetMark implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

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

			if($payload['date'] != date('d.m.Y') && $payload['date'] != date('d.m.Y', strtotime('+1 day'))) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "📛 Данное расписание уже неактуально. (Наступил новый день)" ])
				]);
				return false;
			}

			$lk = new LK(intval($object['user_id']));
			if($lk->auth() != 1) {
				$vkApi->sendMessage("📛 Нет возможности проверить достоверность данных, вызовите список отметок заново.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Вызвать","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"}]],"inline":true}'
				]);
				return false;
			}

			$data = $lk->getSchedule($payload['date']);

			$nums_with_dates = array_column($data['items'], 'num_with_time');
			$teachers = array_column($data['items'], 'teacher');

			if(!in_array($payload['num_with_time'], $nums_with_dates) || !in_array($payload['teacher'], $teachers)) {
				$vkApi->sendMessage("📛 Данные недостоверны, обновите список отметок.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Обновить","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking 1\" }"},"color":"negative"}]],"inline":true}'
				]);
				return false;
			}

			$exp = explode(' ', $payload['num_with_time']);
			if(count($exp) > 1) {
				$time = strtotime($payload['date'].' '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]);
			} else {
				$time = strtotime($payload['date'].' '.explode('-', $payload['num_with_time'])[1]);
			}

			if($time < time()) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "📛 Пара уже закончилась, поставить отметку невозможно." ])
				]);
				return true;
			}

			$db = R::findOne('schedule', 'WHERE `user_id` = ? AND `num_with_time` = ? AND `date` = ? AND `teacher` = ?', [ $object['user_id'], $payload['num_with_time'], $payload['date'], $payload['teacher'] ]);
			if($db == null) {
				$db = R::dispense('schedule');
				$db['user_id'] = $object['user_id'];
				$db['date'] = $payload['date'];
				$db['status'] = 0;
				$db['num_with_time'] = $payload['num_with_time'];
				$db['teacher'] = $payload['teacher'];
				R::store($db);
			}

			$vkApi->get("messages.sendMessageEventAnswer", [
				'peer_id' => $object['peer_id'],
				'user_id' => $object['user_id'],
				'event_id' => $object['event_id'],
				'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "✅ Задача на установку отметки создана." ])
			]);
			$vkApi->editMessage("📚️ Выберите пары на которых хотите отметиться:", $object['conversation_message_id'], $object['peer_id'], Marking::getKeyboardOrCarousel($type, $data, $object, $object['conversation_message_id'], $payload['date']));
			return true;
		}
	}