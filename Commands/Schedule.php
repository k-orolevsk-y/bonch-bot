<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Schedule implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', $object['text']);
			$payload = (array) $object['payload'];

			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			if(date('H') >= 18) {
				$datetime = strtotime("+1 day");
			} else {
				$datetime = time();
			}

			if($msg[1] != null) {
				if(strtotime($msg[1])) {
					$datetime = strtotime($msg[1]);
				} else {
					switch(strtolower($msg[1])) {
						case "завтра":
							$datetime += 86400;
							break;
						case "послезавтра":
							$datetime += 86400*2;
							break;
						case "вчера":
							$datetime -= 86400;
							break;
					}
				}
			}
			$date = date('d.m.Y', $datetime);

			if($object['peer_id'] <= 2000000000) {
				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
				if($user == null) {
					$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
					if($bind == null) {
						$vkApi->sendMessage("🚫 У Вас не привязана группа.\n\nℹ️ Для просмотра расписания, необходимо привязать личный кабинет или номер группы. Вы можете это сделать с помощью этих кнопок:", [ 'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать ЛК","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"positive"}],[{"action":{"type":"text","label":"Привязать группу","payload":"{ \"command\": \"eval\", \"cmd\": \"/group\" }"},"color":"negative"}]],"inline":true}' ]);
						return false;
					} else {
						$group_id = $bind['group_id'];
					}
				} else {
					$group_id = $user['group_id'];
					$settings = json_decode($user['settings'], true);
				}
			} else {
				$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
				if($bind == null) {
					$vkApi->sendMessage("🚫 К данной беседе не привязана группа.", [ 'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать группу","payload":"{ \"command\": \"eval\", \"cmd\": \"/group\" }"},"color":"positive"}]],"inline":true}' ]);
					return false;
				} else {
					$group_id = $bind['group_id'];
				}
			}

			if($payload['update'] == null) {
				$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
				$conversation_message_id = $vkApi->sendMessage("📡 Получаю информацию.", [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward
					]
				)[0]['conversation_message_id'];
			} else {
				$conversation_message_id = $object['conversation_message_id'];
			}

			if(!empty($settings) && !empty($user) && $settings['schedule_from_lk']) {
				$conversation_message_id = $vkApi->editMessage("🙈 Авторизируемся в ЛК.", $conversation_message_id, $object['peer_id']);

				$lk = new LK($user['user_id']);
				$auth = $lk->auth();

				if($auth != 1) {
					$vkApi->editMessage("⚠️ Авторизоваться в ЛК не удалось.", $conversation_message_id, $object['peer_id']);
					return false;
				}

				$schedule = $lk->getSchedule($date);
				if($schedule == null) {
					$vkApi->editMessage("🪦 Не удалось получить расписание из личного кабинета.\n💡 Вы можете временно изменить настройку расписания, чтобы бот получал его с сайта:", $conversation_message_id, $object['peer_id'], [
						'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Настройки профиля","payload":"{ \"command\": \"settings\", \"for\": '.$object['from_id'].' }"},"color":"negative"}]],"inline":true}'
					]);
					return false;
				}
			} else {
				$items = R::getAll('SELECT * FROM `schedule_parse` WHERE `group_id` = ? AND `date` = ? ORDER BY `start`', [ $group_id, $date ]);
				$schedule = [ 'count' => count(array_unique(array_column($items, 'num_with_time'))), 'items' => $items ];
			}

			$keyboard = '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $datetime-86400).'","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime-86400).'\", \"update\": 1 }"},"color":"primary"},{"action":{"type":"callback","label":"Сегодня","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime('today')).'\", \"update\": 1 }"},"color":"positive"},{"action":{"type":"callback","label":"'.date('d.m', $datetime+86400).' ➡️","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime+86400).'\", \"update\": 1 }"},"color":"primary"}],[{"action":{"type":"callback","label":"🔍 Поиск по преподователю","payload":"{ \"command\": \"schedule_teacher\", \"action\": 0 }"},"color":"secondary"}]],"inline":true}';
			if($schedule['count'] < 1) {
				$vkApi->editMessage("⚡️ Пар в данный день нет. ($date)", $conversation_message_id, $object['peer_id'], ['keyboard' => $keyboard]);
				return true;
			}
			$keyboard = '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $datetime-86400).'","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime-86400).'\", \"update\": 1 }"},"color":"primary"},{"action":{"type":"callback","label":"Сегодня","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime('today')).'\", \"update\": 1 }"},"color":"positive"},{"action":{"type":"callback","label":"'.date('d.m', $datetime+86400).' ➡️","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime+86400).'\", \"update\": 1 }"},"color":"primary"}],[{"action":{"type":"callback","label":"🔍 Поиск по преподователю","payload":"{ \"command\": \"schedule_teacher\", \"action\": 0 }"},"color":"secondary"}],[{"action":{"type":"callback","label":"🖼 Картинкой","payload":"{ \"command\": \"schedule_img\", \"time\": \"'.$datetime.'\" }"},"color":"secondary"}]],"inline":true}';

			$day = [ 'воскресенье', 'понедельник', 'вторник', 'среду', 'четверг', 'пятницу', 'субботу' ][date('w', $datetime)];
			$text = "Расписание на $day, $date.";
			if($schedule['week'] != null) {
				$text .= "\nНеделя ${schedule['week']}.";
			}


			$next_lesson = null;
			foreach($schedule['items'] as $key => $lesson) {
				if($next_lesson != null) {
					$next_lesson = null;
					continue;
				}

				if($lesson['place'] != "ауд.: ДОТ") {
					$split = explode(';', $lesson['place']);
					$num = (int) filter_var($split[0], FILTER_SANITIZE_NUMBER_INT);
					$build_info = explode('/', ($split[1] ?? ""));

					if($num > 0 && trim($build_info[0]) == "Б22" && $build_info[1] > 0) {
						$lesson['place'] .= " (https://nav.sut.ru/?cab=k${build_info[1]}-$num)";
					}
				}

				if($lesson['num_with_time'] == $schedule['items'][$key+1]['num_with_time']) {
					$next_lesson = $schedule['items'][$key+1];
					if($next_lesson['place'] != "ауд.: ДОТ") {
						$split = explode(';', $next_lesson['place']);
						$num = (int) filter_var($split[0], FILTER_SANITIZE_NUMBER_INT);
						$build_info = explode('/', ($split[1] ?? ""));

						if($num > 0 && trim($build_info[0]) == "Б22" && $build_info[1] > 0) {
							$next_lesson['place'] .= " (https://nav.sut.ru/?cab=k${build_info[1]}-$num)";
						}
					}
					$lesson['name'] = str_replace([ '(1)', '(2)' ], '', $lesson['name']); // Говнофикс скобок в расписании при английском языке и разным подгруппам

					$text .= "\n\n${lesson['num_with_time']}.\n📚 ${lesson['name']}\n🙋🏻 ${lesson['teacher']}, ${next_lesson['teacher']}\n📖 ${lesson['type']}\n🗺 Аудитория: ${lesson['place']}, ${next_lesson['place']}";
				} else {
					$text .= "\n\n${lesson['num_with_time']}.\n📚 ${lesson['name']}\n🙋🏻 ${lesson['teacher']}\n📖 ${lesson['type']}\n🗺 Аудитория: ${lesson['place']}";
				}
			}

			$vkApi->editMessage($text, $conversation_message_id, $object['peer_id'], ['keyboard' => $keyboard]);
			return true;
		}

	}