<?php
	namespace Me\Korolevsky\BonchBot\Commands;


	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Messages implements Command {

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
				$forward = $msg[1] == "update" ? [] : ['is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
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
				$conversation_message_id = $vkApi->sendMessage("🙈 Авторизируемся в ЛК.", [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward
					]
				)[0]['conversation_message_id'];
			} else {
				$conversation_message_id = $payload['update'];
				if($msg[1] == "update") {
					$deleted = $vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']], 'delete_for_all' => 1]);
					if(!$deleted['response'][$object['conversation_message_id']]) {
						return false;
					}

					$conversation_message_id = $vkApi->sendMessage("🙈 Авторизируемся в ЛК.", [
							'peer_ids' => $object['peer_id'],
							'forward' => $forward
						]
					)[0]['conversation_message_id'];
				} elseif(!is_numeric($msg[1])) {
					$vkApi->editMessage("🙈 Авторизируемся в ЛК.", $conversation_message_id, $object['peer_id']);
				}
			}

			if($payload['delete_ids'] != null) {
				$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => $payload['delete_ids'], 'delete_for_all' => 1]);
			}

			$lk = new LK($object['from_id']);
			$auth = $lk->auth();

			if($auth != 1) {
				$vkApi->editMessage("⚠️ Авторизоваться в ЛК не удалось.", $conversation_message_id, $object['peer_id']);
			}

			$cache = R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [$object['from_id'], "messages"]);
			if($cache == null || $msg[1] == "update" || $cache['time'] < (time() - 1800)) {
				if($cache != null) {
					R::trash($cache);
				}

				$api->end(true);
				$vkApi->editMessage("📘 Получаю сообщения из ЛК...\nℹ️ Это довольно затратная функция, получение сообщений происходит в течении 15 секунд.", $conversation_message_id, $object['peer_id']);

				$data = $lk->getMessages();
				if($data == null) {
					$vkApi->editMessage("❌ Не удалось получить данные из ЛК.", $conversation_message_id, $object['peer_id']);
					return false;
				}

				$cache = R::dispense('cache');
				$cache['user_id'] = $object['from_id'];
				$cache['time'] = time();
				$cache['name'] = "messages";
				$cache['data'] = json_encode($data);
				R::store($cache);
			} else {
				$data = json_decode($cache['data'], true);
			}

			if($data['count'] < 1) {
				$vkApi->editMessage("🤔 У Вас нет сообщений в ЛК.", $conversation_message_id, $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Обновить","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages\", \"update\": '.$conversation_message_id.' }"},"color":"secondary"}]],"inline":true}'
				]);
				return false;
			}

			$vkApi->editMessage(
				"⚙️ В ЛК у Вас есть сообщения с " . $api->pluralForm(count($data['sorted_messages']), [ 'адресатом', 'адресатами', 'адресатами' ]) . ", выберите адресата:",
				$conversation_message_id, $object['peer_id'],
				[
					'keyboard' => self::generateKeyboard($data, $object, $conversation_message_id, is_numeric($msg[1]) && $payload['update'] != null ? intval($msg[1]) : 0)
				]
			);
			return true;
		}

		public static function generateKeyboard(array $data, array $object, int $conversation_message_id, int $offset = 0): string {
			$keyboard = [ 'inline' => true, 'buttons' => [] ];
			$sorted_messages = array_slice($data['sorted_messages'], $offset, 6);
			$generator_key = ['key' => 0, 'count' => 0];

			foreach($sorted_messages as $target => $messages) {
				$split = explode(' ', $target);
				if(count($split) > 2) {
					$name = "${split[0]} " . mb_substr($split[1], 0, 1) . ". " . mb_substr($split[2], 0, 1) . ".";
				} else {
					$name = "${split[0]} " . mb_substr($split[1], 0, 1) . ". ";
				}

				if($generator_key['count'] >= 2) {
					$generator_key['key'] += 1;
					$generator_key['count'] = 0;
				}

				$keyboard['buttons'][$generator_key['key']][] = [
					'action' => [
						'type' => 'callback',
						'label' => $name,
						'payload' => json_encode(['command' => 'get_messages', 'target' => $target, 'oc' => $offset])
					],
					'color' => 'primary'
				];
				$generator_key['count'] += 1;
			}

			if(count($data['sorted_messages']) > 8) {
				if($offset == 0) {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => 'Далее',
							'payload' => json_encode([ 'command' => 'eval', 'cmd' => "/messages " . $offset+6, 'update' => $conversation_message_id ])
						],
						'color' => 'positive'
					];
				} else {
					if(count(array_slice($data['sorted_messages'], $offset+6, 6)) < 1) {
						$keyboard['buttons'][][] = [
							'action' => [
								'type' => 'callback',
								'label' => 'Назад',
								'payload' => json_encode([ 'command' => 'eval', 'cmd' => "/messages " . $offset-6, 'update' => $conversation_message_id ])
							],
							'color' => 'negative'
						];
					} else {
						$keyboard['buttons'][] = [
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Назад',
									'payload' => json_encode([ 'command' => 'eval', 'cmd' => "/messages " . $offset-6, 'update' => $conversation_message_id ])
								],
								'color' => 'negative'
							],
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Далее',
									'payload' => json_encode([ 'command' => 'eval', 'cmd' => "/messages " . $offset+6, 'update' => $conversation_message_id ])
								],
								'color' => 'positive'
							]
						];
					}
				}
			}

			$keyboard['buttons'][][] = [
				'action' => [
					'type' => 'callback',
					'label' => 'Обновить список',
					'payload' => json_encode([ 'command' => 'eval', 'cmd' => "/messages update", 'update' => $conversation_message_id ])
				],
				'color' => 'secondary'
			];

			return json_encode($keyboard);
		}

	}