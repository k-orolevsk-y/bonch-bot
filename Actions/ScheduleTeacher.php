<?php
	namespace Me\Korolevsky\BonchBot\Actions;


	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Action;
	use RedBeanPHP\R;

	class ScheduleTeacher implements Action {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();
			$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => [$payload['reply_message_id']], 'delete_for_all' => 1]);

			if($api->cM($object['text'], [ 'гандон', 'уебок', 'чмо', 'пидорас', 'gun done' ])) {
				$object['text'] = 'старостин'; // рофл над старостиным
			}

			$db = R::findOne('schedule_parse', 'WHERE `teacher` LIKE ?', [ "%${object['text']}%" ]);
			if($db == null) {
				return $vkApi->sendMessage("🚫 Преподователь не найден.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"🔍 Попробовать ещё раз","payload":"{ \"command\": \"schedule_teacher\", \"action\": 0 }"},"color":"secondary"}]],"inline":true}'
				]);
			}

			if(date('H') >= 18) {
				$datetime = strtotime("+1 day");
			} else {
				$datetime = time();
			}

			$schedule = R::getAll('SELECT * FROM `schedule_parse` WHERE `teacher` LIKE ? AND `date` = ? ORDER BY `num_with_time`', [ "%${object['text']}%", date('d.m.Y', $datetime) ]);
			if($schedule == null) {
				return $vkApi->sendMessage("⚡️ У данного преподователя в этот день (".date('d.m.Y', $datetime).") нет пар.", [
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
				$text .= "\n\n$group -- ${lesson['num_with_time']}.\n📚 ${lesson['name']}\n📖 ${lesson['type']}\n🗺 Аудитория: ${lesson['place']}";
			}

			$vkApi->sendMessage($text, [
				'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $datetime-86400).'","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime-86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"},{"action":{"type":"callback","label":"'.date('d.m', $datetime+86400).' ➡️","payload":"{ \"command\": \"schedule_teacher\", \"action\": 1, \"date\": \"'.date('d.m.Y', $datetime+86400).'\", \"teacher\": \"'.$db['teacher'].'\" }"},"color":"secondary"}]],"inline":true}'
			]);
			return true;
		}

	}