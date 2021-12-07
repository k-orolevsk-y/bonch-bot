<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Command;
	use Me\Korolevsky\BonchBot\LK;
	use RedBeanPHP\R;

	class Marking implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', $object['text']);
			$payload = (array)$object['payload'];

			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("⚙️ Информация отправлена Вам в личные сообщения.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);

				$forward = [];
				$object['peer_id'] = $object['from_id'];
			} else {
				$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($user == null) {
				$vkApi->sendMessage("📛 У Вас не привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"secondary"}]],"inline":true}',
					'peer_id' => $object['peer_id'],
					'forward' => $forward
				]);
				return false;
			}

			if($payload['update'] == null) {
				$conversation_message_id = $vkApi->sendMessage("📘 Получение данных из ЛК...", [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward
					]
				)[0]['conversation_message_id'];
			} else {
				$conversation_message_id = $payload['update'];
			}

			if(in_array(mb_strtolower($msg[1]), ['завтра', 'tomorrow'])) {
				$date = date('d.m.Y', strtotime("+1 day"));
			} else {
				$date = date('d.m.Y');
			}

			$cache = R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [$object['from_id'], "schedule-$date"]);
			if($cache == null || end($msg) == 1) {
				if($cache != null) {
					R::trash($cache);
				}

				$lk = new LK($user['user_id']);
				$lk->auth();
				$data = $lk->getSchedule($date);

				if($data === false) {
					$vkApi->editMessage("❌ Не удалось получить данные из ЛК.", $conversation_message_id, $object['peer_id']);
					return false;
				}

				$cache = R::dispense('cache');
				$cache['user_id'] = $object['from_id'];
				$cache['name'] = "schedule-$date";
				$cache['data'] = json_encode($data);
				R::store($cache);
			} else {
				$data = json_decode($cache['data'], true);
			}

			if($data['count'] < 1) {
				$today = $date == date('d.m.Y');

				$vkApi->editMessage("😄 " . ($today ? "Сегодня" : "Завтра") . " (${date}) пар нет.", $conversation_message_id, $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Обновить","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking' . (!$today ? " tomorrow" : "") . ' 1\", \"update\": ' . $conversation_message_id . ' }"},"color":"secondary"}]],"inline":true}'
				]);
				return true;
			}

			$type = json_decode($user['settings'], true)['type_marking'] == 0 ? "carousel" : "keyboard";
			$vkApi->editMessage("⚙️ Выберите пары на которых хотите отметиться:", $conversation_message_id, $object['peer_id'], self::getKeyboardOrCarousel($type, $data, $object, $conversation_message_id, $date));
			return true;
		}


		public static function getKeyboardOrCarousel(string $type = "carousel", array $data, array $object, int $conversation_message_id, string $date): array {
			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			if($type == "carousel") {
				$carousel = [ 'type' => 'carousel', 'elements' => [] ];
				foreach($data['items'] as $item) {
					$exp = explode(' ', $item['num_with_time']);
					if(count($exp) > 1) {
						$time = strtotime($date.' '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]);
					} else {
						$time = strtotime($date.' '.explode('-', $item['num_with_time'])[1]);
					}
					$schedule = R::findOne('schedule', 'WHERE `user_id` = ? AND `num_with_time` = ? AND `date` = ?', [ $object['from_id'], $item['num_with_time'], $date ]);

					if($time < time()) {
						$carousel['elements'][] = [
							'title' => $item['name'],
							'description' => "${item['num_with_time']}\n${item['type']} (${item['place']})",
							'buttons' => [[
								'action' => [
									'type' => 'callback',
									'label' => $schedule['status'] == 1000 ? 'Отметка уже поставлена' : 'Невозможно отметиться',
									'payload' => json_encode(['command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id,])
								],
								'color' => $schedule['status'] == 1000 ? 'primary' : 'secondary'
							]]
						];
					} elseif($schedule == null) {
						$carousel['elements'][] = [
							'title' => $item['name'],
							'description' => "${item['num_with_time']}\n${item['type']} (${item['place']})",
							'buttons' => [[
								'action' => [
									'type' => 'callback',
									'label' => 'Отметиться',
									'payload' => json_encode(['command' => 'set_mark', 'num_with_time' => $item['num_with_time'], 'update' => $conversation_message_id, 'date' => $date])
								],
								'color' => 'positive'
							]]
						];
					} elseif($schedule['status'] == -1) {
						$carousel['elements'][] = [
							'title' => $item['name'],
							'description' => "${item['num_with_time']}\n${item['type']} (${item['place']})",
							'buttons' => [[
								'action' => [
									'type' => 'callback',
									'label' => 'Невозможно отметиться',
									'payload' => json_encode(['command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id])
								],
								'color' => 'secondary'
							]]
						];
					} else {
						$carousel['elements'][] = [
							'title' => $item['name'],
							'description' => "${item['num_with_time']}\n${item['type']} (${item['place']})",
							'buttons' => [[
								'action' => [
									'type' => 'callback',
									'label' => $schedule['status'] == 1000 ? 'Отметка уже поставлена' : 'Не отмечать',
									'payload' => json_encode($schedule['status'] == 1000 ? ['command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id] : ['command' => 'del_mark', 'mark_id' => $schedule['id'], 'update' => $conversation_message_id, 'date' => $date])
								],
								'color' => $schedule['status'] == 1000 ? 'primary' : 'negative'
							]]
						];
					}
				}

				return [ 'template' => json_encode($carousel) ];
			}

			$keyboard = [ 'buttons' => [], 'inline' => true ];
			foreach($data['items'] as $item) {
				$exp = explode(' ', $item['num_with_time']);
				if(count($exp) > 1) {
					$time = strtotime($date.' '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]);
				} else {
					$time = strtotime($date.' '.explode('-', $item['num_with_time'])[1]);
				}
				$schedule = R::findOne('schedule', 'WHERE `user_id` = ? AND `num_with_time` = ? AND `date` = ?', [ $object['from_id'], $item['num_with_time'], $date ]);
				$name = @iconv_strlen($item['name']) >= 40 ? mb_substr($item['name'], 0, 36) . "..." : $item['name'];

				if($time < time()) {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => $name,
							'payload' => json_encode([ 'command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id ])
						],
						'color' => $schedule['status'] == 1000 ? 'primary' : 'secondary'
					];
				} elseif($schedule == null) {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => $name,
							'payload' => json_encode([ 'command' => 'set_mark', 'num_with_time' => $item['num_with_time'], 'update' => $conversation_message_id, 'date' => $date ])
						],
						'color' => 'positive'
					];
				} elseif($schedule['status'] == -1) {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => $name,
							'payload' => json_encode([ 'command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id ])
						],
						'color' => 'secondary'
					];
				} else {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => $name,
							'payload' => json_encode($schedule['status'] == 1000 ? [ 'command' => 'eval', 'cmd' => '/marking', 'update' => $conversation_message_id ] : [ 'command' => 'del_mark', 'mark_id' => $schedule['id'], 'update' => $conversation_message_id, 'date' => $date ])
						],
						'color' => $schedule['status'] == 1000 ? 'primary' : 'negative'
					];
				}
			}

			return [ 'keyboard' => json_encode($keyboard) ];
		}
	}