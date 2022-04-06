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
				$vkApi->editMessage("📛 Нет возможности проверить достоверность данных, вызовите список отметок заново.", $object['conversation_message_id'], $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Вызвать","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"}]],"inline":true}'
				]);
				return false;
			}

			$data = $lk->getSchedule($payload['date']);
			$item = $data['items'][$payload['key']];

			if($item == null) {
				$vkApi->editMessage("📛 Данные недостоверны, обновите список отметок.", $object['conversation_message_id'], $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Обновить","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking 1\" }"},"color":"negative"}]],"inline":true}'
				]);
				return false;
			}

			$exp = explode(' ', $item['num_with_time']);
			if(count($exp) > 1) {
				$time = strtotime($payload['date'].' '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]);
			} else {
				$time = strtotime($payload['date'].' '.explode('-', $item['num_with_time'])[1]);
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

			$db = R::findOne('schedule', 'WHERE `user_id` = ? AND `num_with_time` = ? AND `date` = ? AND `teacher` = ?', [ $object['user_id'], $item['num_with_time'], $payload['date'], $item['teacher'] ]);
			if($db != null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "⚡️ Задача на установку отметки уже создана!" ])
				]);

				$vkApi->sendMessage("📚️ Выберите пары на которых хотите отметиться:", Marking::getKeyboardOrCarousel($type, $data, $object, 0, $payload['date']));
				$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']], 'delete_for_all' => 1]);

				return true;
			}

			$db = R::dispense('schedule');
			$db['user_id'] = $object['user_id'];
			$db['date'] = $payload['date'];
			$db['status'] = 0;
			$db['num_with_time'] = $item['num_with_time'];
			$db['teacher'] = $item['teacher'];
			R::store($db);

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