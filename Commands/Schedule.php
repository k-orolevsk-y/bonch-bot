<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Command;
	use RedBeanPHP\R;

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
						$vkApi->sendMessage("🚫 У Вас не привязана группа.\n\nℹ️ Чтобы посмотреть расписание, необходимо привязать ЛК/группу.\n❔ Чтобы привязать ЛК воспользуйтесь командой: /привязать; чтобы привязать группу воспользуйтесь командой: /группа");
						return false;
					} else {
						$group_id = $bind['group_id'];
					}
				} else {
					$group_id = $user['group_id'];
				}
			} else {
				$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
				if($bind == null) {
					$vkApi->sendMessage("🚫 К данной беседе не привязана группа.\n\nℹ️ Чтобы посмотреть расписание, необходимо привязать группу.\n❔ Чтобы привязать группу воспользуйтесь командой: /группа");
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

			$schedule = R::findOne('cache', 'WHERE `name` = ?', [ "all-schedule-$group_id-$date" ]);
			if($schedule == null) {
				$schedule = $api->sendBonchRequest("schedule.get", [ 'group_id' => $group_id, 'date' => $date ]);
				if(!$schedule['ok']) {
					$vkApi->editMessage("⚙️ Повторите попытку позже.", $conversation_message_id, $object['peer_id']);
					return false;
				}

				$db = R::dispense('cache');
				$db['user_id'] = 0;
				$db['name'] = "all-schedule-$group_id-$date";
				$db['data'] = json_encode($schedule);
				R::store($db);
			} else {
				$schedule = json_decode($schedule['data'], true);
			}

			$schedule = $schedule['response'];
			$keyboard = '{"buttons":[[{"action":{"type":"callback","label":"Назад '.date('d.m.Y', $datetime-86400).'","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime-86400).'\", \"update\": 1 }"},"color":"secondary"},{"action":{"type":"callback","label":"Вперед '.date('d.m.Y', $datetime+86400).'","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $datetime+86400).'\", \"update\": 1 }"},"color":"secondary"}],[{"action":{"type":"callback","label":"« ПН","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime("monday this week")).'\", \"update\": 1 }"},"color":"primary"},{"action":{"type":"callback","label":"Сегодня","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime('today')).'\", \"update\": 1 }"},"color":"secondary"},{"action":{"type":"callback","label":"ПТ »","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime('friday this week')).'\", \"update\": 1 }"},"color":"primary"}]],"inline":true}';

			if($schedule['count'] < 1) {
				$vkApi->editMessage("😄 $date пар нет.", $conversation_message_id, $object['peer_id'], ['keyboard' => $keyboard]);
				return true;
			}

			$text = "ℹ️ Расписание на $date:\n\n";
			foreach($schedule['items'] as $lesson) {
				if(count($lesson['classes']) > 1) {
					$lessons = $lesson['classes'];
					$classes = [
						'name' => [],
						'teacher' => [],
						'audience' => []
					];

					foreach($lessons as $_lesson) {
						$classes['name'][] = $_lesson['name'];
						$classes['teacher'][] = $_lesson['teacher'];
						$classes['type'] = $_lesson['type'];

						$audience = $_lesson['audience'];
						if($_lesson['navigator'] != null) {
							$audience .= " (${_lesson['navigator']})";
						}
						$classes['audience'][] = $audience;
					}

					$classes['name'] = implode(', ', $classes['name']);
					$classes['teacher'] = implode(', ', $classes['teacher']);
					$classes['audience'] = implode(', ', $classes['audience']);
				} else {
					$classes = $lesson['classes'][0];
					if($classes['navigator'] != null) {
						$classes['audience'] .= " (${classes['navigator']})";
					}
				}

				if($lesson['num'] < 1) {
					$lesson['num'] = "";
				} else {
					$lesson['num'] = "${lesson['num']}.";
				}

				$classes['audience'] = str_replace('ауд.: ', '', $classes['audience']);
				$text .= "👀 ${lesson['num']} ${classes['name']}\n🕙 Время: с ${lesson['start']} до ${lesson['end']}\n🙋🏻 Преподователь: ${classes['teacher']}\n📖 Тип: ${classes['type']}\n🗺 Аудитория: ${classes['audience']}\n\n";
			}

			$vkApi->editMessage($text, $conversation_message_id, $object['peer_id'], ['keyboard' => $keyboard]);
			return true;
		}

	}