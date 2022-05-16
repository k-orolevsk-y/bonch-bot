<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Exception;
	use Imagick;
	use ImagickDraw;
	use ImagickPixel;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use Me\Korolevsky\BonchBot\LK;

	class ScheduleImg implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();
			if($payload['time'] == null) {
				return $vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🚫 Ошибка проверки параметров." ])
				]);
			}

			$date = date('d.m.Y', $payload['time']);
			if($object['peer_id'] <= 2000000000) {
				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['user_id'] ]);
				if($user == null) {
					$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
					if($bind == null) {
						$vkApi->editMessage("🚫 У Вас не привязана группа.\n\nℹ️ Чтобы посмотреть расписание, необходимо привязать ЛК/группу.\n❔ Чтобы привязать ЛК воспользуйтесь командой: /привязать; чтобы привязать группу воспользуйтесь командой: /группа", $object['conversation_message_id'], $object['peer_id']);
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
					$vkApi->editMessage("🚫 К данной беседе не привязана группа.\n\nℹ️ Чтобы посмотреть расписание, необходимо привязать группу.\n❔ Чтобы привязать группу воспользуйтесь командой: /группа", $object['conversation_message_id'], $object['peer_id']);
					return false;
				} else {
					$group_id = $bind['group_id'];
				}
			}

			if(!empty($settings) && !empty($user) && $settings['schedule_from_lk']) {
				$lk = new LK($user['user_id']);
				$auth = $lk->auth();

				if($auth != 1) {
					return $vkApi->get("messages.sendMessageEventAnswer", [
						'peer_id' => $object['peer_id'],
						'user_id' => $object['user_id'],
						'event_id' => $object['event_id'],
						'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "⚠️ Авторизоваться в ЛК не удалось." ])
					]);
				}

				$schedule = $lk->getSchedule($date);
				if($schedule == null) {
					return $vkApi->get("messages.sendMessageEventAnswer", [
						'peer_id' => $object['peer_id'],
						'user_id' => $object['user_id'],
						'event_id' => $object['event_id'],
						'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🪦 Не удалось получить расписание из личного кабинета." ])
					]);
				}
			} else {
				$items = R::getAll('SELECT * FROM `schedule_parse` WHERE `group_id` = ? AND `date` = ? ORDER BY `start`', [ $group_id, $date ]);
				$schedule = [ 'count' => count(array_unique(array_column($items, 'num_with_time'))), 'items' => $items ];
			}

			$vkApi->get("messages.sendMessageEventAnswer", [
				'peer_id' => $object['peer_id'],
				'user_id' => $object['user_id'],
				'event_id' => $object['event_id'],
				'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "🖼 Формируем расписание картинкой, скоро отправим!" ])
			]);

			foreach($schedule['items'] as $key => $lesson) {
				if(iconv_strlen($lesson['teacher']) > 40) {
					$schedule['items'][$key]['teacher'] = mb_strcut($lesson['teacher'], 0, 39) . "...";
				}
			}

			$img = $this->createImage($schedule, $payload['time'], R::findOne('groups', 'WHERE `id` = ?', [ $group_id ])['name']);
			if($img == null) {
				return $vkApi->sendMessage('😔 Не удалось загрузить фотографию расписания :(');
			}

			try {
				$address = $vkApi->getClient()->photos()->getMessagesUploadServer(Data::TOKENS['public']);
				$photo = $vkApi->getClient()->getRequest()->upload($address['upload_url'], 'photo', $img);
				$response_save_photo = $vkApi->getClient()->photos()->saveMessagesPhoto(Data::TOKENS['public'], [
					'server' => $photo['server'],
					'photo'  => $photo['photo'],
					'hash'   => $photo['hash'],
				])[0];

				$attachment = "photo${response_save_photo['owner_id']}_${response_save_photo['id']}_${response_save_photo['access_key']}";
				unlink($img);
			} catch(Exception) {
				unlink($img);
				return $vkApi->sendMessage('😔 Не удалось загрузить фотографию расписания :(');
			}

			$text = explode("\n\n", $vkApi->useMethod("messages", "getByConversationMessageId", [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => $object['conversation_message_id'] ])['items'][0]['text'])[0];
			$keyboard = '{"buttons":[[{"action":{"type":"callback","label":"⬅️ '.date('d.m', $payload['time']-86400).'","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $payload['time']-86400).'\", \"update\": 1 }"},"color":"primary"},{"action":{"type":"callback","label":"Сегодня","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', strtotime('today')).'\", \"update\": 1 }"},"color":"positive"},{"action":{"type":"callback","label":"'.date('d.m', $payload['time']+86400).' ➡️","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule '.date('d.m.Y', $payload['time']+86400).'\", \"update\": 1 }"},"color":"primary"}],[{"action":{"type":"callback","label":"🔍 Поиск по преподователю","payload":"{ \"command\": \"schedule_teacher\", \"action\": 0 }"},"color":"secondary"}]],"inline":true}';

			return $vkApi->editMessage($text, $object['conversation_message_id'], $object['peer_id'], [ 'attachment' => $attachment, 'keyboard' => $keyboard ]);
		}

		protected function createImage(array $data, int $time, string $group): ?string {
			try {
				$width = 780;
				$height = $data['count'] > 0 ? 230.5 + (230.5*$data['count']) : 300;

				$img = new Imagick();
				$img->newImage($width, $height, new ImagickPixel('white'));

				// Header

				$draw = new ImagickDraw();
				$draw->setFont('Files/SFM.ttf');
				$draw->setFontSize(54);
				$img->annotateImage($draw, 30, 100, 0, $group);

				$day = [ 'Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота' ][date('w', $time)];
				$month = [ '', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря' ][date('n', $time)];

				$draw = new ImagickDraw();
				$draw->setFont('Files/SFL.ttf');
				$draw->setFontSize(36);
				$draw->setFillColor(new ImagickPixel('gray'));
				$img->annotateImage($draw, 30, 150, 0, date("$day, d $month Y", $time));

				$logo = new Imagick("Files/logo.png");
				$logo->resizeImage(236, 132, 1, 0);
				$img->compositeImage($logo, $logo->getImageColorspace(), $width-215, 40);

				$draw = new ImagickDraw();
				$draw->line(30, 170, 750, 170);
				$img->drawImage($draw);

				// Header

				// Main

				$y = 170;
				$next_lesson = null;

				foreach($data['items'] as $key => $item) {
					if($next_lesson != null) {
						$next_lesson = null;
						continue;
					}

					if($item['num_with_time'] == $data['items'][$key+1]['num_with_time']) {
						$next_lesson = $data['items'][$key+1];

						$item['name'] = str_replace([ '(1)', '(2)' ], '', $item['name']); // Говнофикс скобок в расписании при английском языке и разным подгруппам
						$item['teacher'] .= ", ${next_lesson['teacher']}";
						$item['place'] .= ", ${next_lesson['place']}";
					}


					$draw = new ImagickDraw();
					$draw->setStrokeColor(new ImagickPixel(
						$item['type'] == "Лекция" ? 'rgb(0, 108, 183)' : ($item['type'] == "Практические занятия" ? 'rgb(103, 189, 69)' : 'rgb(237, 21, 86)')
					));
					$draw->setStrokeWidth(8);
					$draw->line(150, $y+10, 150, $y+220);
					$img->drawImage($draw);


					// Num and time
					$exp = explode(' ', $item['num_with_time']);

					if(is_numeric($exp[0])) {
						$num = intval($exp[0]);
						$time = explode('-', str_replace([ '(', ')' ], '', $exp[1]));
					} else {
						$num = "ФЗ";
						$time = explode('-', str_replace('.', ':', $item['num_with_time']));
					}


					$draw = new ImagickDraw();
					$draw->setFont('Files/SFM.ttf');
					$draw->setFontSize($num == "ФЗ" ? 56 : 72);
					$img->annotateImage($draw, $num == "ФЗ" ? 30 : 35, $y+120, 0, $num);

					$draw = new ImagickDraw();
					$draw->setFont('Files/SFL.ttf');
					$draw->setFontSize(28);
					$img->annotateImage($draw, 35, $y+175, 0, $time[0]);
					$img->annotateImage($draw, 35, $y+210, 0, $time[1]);
					// Num and time

					// Name
					$size = 0;
					$name = "";
					$exp = explode(' ', $item['name']);

					foreach($exp as $str) {
						$size += iconv_strlen($str);
						if($size >= 24) {
							$name .= "\n $str";
							$size = 0;
						} else {
							$name .= " $str";
						}
					}
					$y_next_text = count(explode("\n", $name))*38;

					$draw = new ImagickDraw();
					$draw->setFont('Files/SFM.ttf');
					$draw->setFontSize(36);
					$draw->setTextInterLineSpacing(-10);
					$img->annotateImage($draw, 160, $y+50, 0, $name);
					// Name

					// Place and Type
					$draw = new ImagickDraw();
					$draw->setFont('Files/SFL.ttf');
					$draw->setFontSize(28);
					$img->annotateImage($draw, 166, $y+50+$y_next_text, 0, $item['place']);
					$img->annotateImage($draw, 166, $y+85+$y_next_text, 0, $item['type']);
					// Place and Type

					// Teacher
					$draw = new ImagickDraw();
					$draw->setFont('Files/SFL.ttf');
					$draw->setFontSize(28);
					$img->annotateImage($draw, 166, $y+120+$y_next_text, 0, $item['teacher']);
					// Teacher

					$y += 230;

					$draw = new ImagickDraw();
					$draw->line(30, $y, 750, $y);
					$img->drawImage($draw);
				}

				// Main


				// Footer

				$draw = new ImagickDraw();
				$draw->setFont("Files/SFL.ttf");
				$draw->setFontSize(24);
				$draw->setFillColor(new ImagickPixel('gray'));
				$img->annotateImage($draw, 590, $height-20, 0, "vk.com/botbonch");

				// Footer


				$img->setImageFormat('png');
				$img->resizeImage($width, $height, 1, 0);

				$filename = 'Files/'.uniqid().'.png';
				$img->writeImage($filename);
			} catch(Exception) {
				return null;
			}

			return $filename;
		}


	}