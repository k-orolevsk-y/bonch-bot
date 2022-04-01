<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use RedBeanPHP\R;

	class ScheduleTeacher implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if($payload['action'] == 0) {
				return $vkApi->editMessage("🔍 Ответьте на данное сообщение, указав имя преподователя.", $object['conversation_message_id'], $object['peer_id'], [
					'payload' => json_encode(['action' => 'schedule_teacher'])
				]);
			} elseif($payload['action'] == 1) {
				$db = R::findOne('schedule_parse', 'WHERE `teacher` LIKE ?', [ "%${payload['teacher']}%" ]);
				if($db == null) {
					return $vkApi->editMessage("🚫 Преподователь не найден.", $object['conversation_message_id'], $object['peer_id'], [
						'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"🔍 Попробовать ещё раз","payload":"{ \"command\": \"schedule_teacher\", \"action\": 0 }"},"color":"secondary"}]],"inline":true}'
					]);
				}

				$datetime = strtotime($payload['date']);
				$schedule = R::getAll('SELECT * FROM `schedule_parse` WHERE `teacher` LIKE ? AND `date` = ? ORDER BY `num_with_time`', [ "%${db['teacher']}%", date('d.m.Y', $datetime) ]);

				if($schedule == null) {
					return $vkApi->editMessage("⚡️ У данного преподователя в этот день (".date('d.m.Y', $datetime).") нет пар.", $object['conversation_message_id'], $object['peer_id'], [
						'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $datetime-86400).'","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime-86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"},{"action":{"type":"callback","label":"'.date('d.m', $datetime+86400).' ➡️","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime+86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"}]],"inline":true}'
					]);
				}

				$groups = [];
				foreach($schedule as $key => $lesson) {
					unset($schedule[$key]['group_id']);
					if($lesson['num_with_time'] == $schedule[$key+1]['num_with_time']) {
						unset($schedule[$key]);
						$groups[] = R::findOne('groups', 'WHERE `id` = ?', [ $lesson['group_id'] ])['name'];
					} else {
						if($groups == null) {
							$schedule[$key]['group'] = R::findOne('groups', 'WHERE `id` = ?', [ $lesson['group_id'] ])['name'];
						} else {
							$groups[] = R::findOne('groups', 'WHERE `id` = ?', [ $lesson['group_id'] ])['name'];
							$schedule[$key]['group'] = implode(', ', $groups);
							$groups = [];
						}
					}
				}

				$day = [ 'воскресенье', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу' ][date('w', $datetime)];
				$text = "Преподователь ${db['teacher']}\nРасписание на $day, ".date('d.m.Y', $datetime).".";

				foreach($schedule as $lesson) {
					if($lesson['place'] != "ауд.: ДОТ") {
						$split = explode(';', $lesson['place']);
						$num = (int) filter_var($split[0], FILTER_SANITIZE_NUMBER_INT);
						$build_info = explode('/', ($split[1] ?? ""));

						if($num > 0 && trim($build_info[0]) == "Б22" && $build_info[1] > 0) {
							$lesson['place'] .= " (https://nav.sut.ru/?cab=k${build_info[1]}-$num)";
						}
					}

					$group = '[club'.Data::GROUP_ID.'|<<'.$lesson['group'].'>>]';
					$text .= "\n\n$group -- ${lesson['num_with_time']}:\n📚 ${lesson['name']}\n📖 ${lesson['type']}\n🗺 Аудитория: ${lesson['place']}";
				}

				return $vkApi->editMessage($text, $object['conversation_message_id'], $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $datetime-86400).'","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime-86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"},{"action":{"type":"callback","label":"'.date('d.m', $datetime+86400).' ➡️","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime+86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"}]],"inline":true}'
				]);
			} else {
				return $vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🚫 Неизвестная ошибка." ])
				]);
			}
		}

	}