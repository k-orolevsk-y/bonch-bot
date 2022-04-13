<?php

	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Exception;
	use Me\Korolevsky\BonchBot\LK;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class GetMessages implements Keyboard {

		private Api $api;

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();
			$this->api = $api;

			if($payload['u'] != null) {
				$object['conversation_message_id'] = $payload['u'];
			}

			if($payload['d'] != null) {
				$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => $payload['d'], 'delete_for_all' => 1]);
			}

			$text = $vkApi->useMethod("messages", "getByConversationMessageId", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]])['items'][0]['text'];
			if($api->cM($text, "✏️ Формируем список сообщений.")) {
				return true;
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [$object['user_id']]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных."])
				]);
				return false;
			}

			$lk = new LK(intval($user['user_id']));
			$auth = $lk->auth();

			if($auth != 1) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "🚫 Не удалось авторизоваться в ЛК."])
				]);
				return false;
			}

			$cache = R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [$object['user_id'], "messages"]);
			if($cache == null) {
				$vkApi->editMessage("📛 В базе данных нет актуальных данных, запросите сообщения заново.", $object['conversation_message_id'], $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Вызвать","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages update\" }"},"color":"negative"}]],"inline":true}'
				]);
				return false;
			}
			

			$messages = json_decode($cache['data'], true);
			if($messages['sorted_messages'][$payload['target']] == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Адресат не найден."])
				]);
				return false;
			}
			$vkApi->editMessage("✏️ Формируем список сообщений.", $object['conversation_message_id'], $object['peer_id']);

			$original_sorted_messages = $messages['sorted_messages'][$payload['target']];
			$sorted_messages = array_slice($original_sorted_messages, 0 + $payload['o'], 5);
			$result = [];


			foreach($sorted_messages as $message) {
				$group_id = Data::GROUP_ID;

				$message['text'] = $lk->getMessageText($message['id']);
				if($message['receiver'] == null) {
					if($message['sender'] == null) {
						$message['sender'] = "Неизвестно";
					}

					$text = "🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] == null ? "Без текста" : $message['text']);
				} else {
					$text = "🙇🏻 Отправитель: [id${object['user_id']}|Вы]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] == null ? "Без текста" : $message['text']);
				}
				$files = self::getFiles($message['files'], $object['user_id']);

				$result[] = [
					'text' => $text,
					'attachment' => $files
				];
			}

			$ids = [];
			$vkApi->editMessage("💬 " . $api->pluralForm(count($sorted_messages), ["сообщение отправлено", "сообщения отправлены", "сообщений отправлено"]) . " из ЛК. " . ($payload['o'] > 1 && count($original_sorted_messages) > 5 ? "(Отступ ${payload['o']})" : ""), $object['conversation_message_id'], $object['peer_id']);

			foreach($result as $message) {
				$ids[] = $vkApi->sendMessage($message['text'], ['attachment' => $message['attachment'], 'forward' => [], 'peer_ids' => $object['peer_id']])[0]['conversation_message_id'];;
			}

			$ids[] = $vkApi->sendMessage("...", ['forward' => [], 'peer_ids' => $object['peer_id']])[0]['conversation_message_id'];;
			$vkApi->editMessage("ℹ️ Выберите дальнейшей действие:", end($ids), $object['peer_id'], ['keyboard' => self::generateKeyboard($ids, $object['conversation_message_id'], [$payload['oc'] ?? 0, $payload['o'] ?? 0, $original_sorted_messages, $payload['target']])]);

			return true;
		}

		private function getFiles(array $files, int $user_id): string {
			$result = [];
			foreach($files as $file) {
				$cache = R::findOne('files', 'WHERE `url` = ?', [ $file ]);
				if($cache != null) {
					$result[] = $cache['doc'];
					continue;
				}

				try {
					$file_name = basename($file);
					file_put_contents("Files/$file_name", file_get_contents($file));

					$address = $this->api->getVkApi()->get("docs.getMessagesUploadServer", ['peer_id' => $user_id, 'type' => 'doc'])['response']['upload_url'];
					if($address == null) {
						throw new Exception(code: 0);
					}
					$uploaded_doc = $this->api->getVkApi()->getClient()->getRequest()->upload($address, 'file', "Files/${file_name}")['file'];
					if($uploaded_doc == null) {
						throw new Exception(code: 1);
					}
					$document = $this->api->getVkApi()->get("docs.save", ['file' => $uploaded_doc, 'title' => "Файл `$file_name` из ЛК"])['response']['doc'];
					if($document == null) {
						throw new Exception(code: 1);
					}

					unlink("Files/$file_name");

					$doc = "doc${document['owner_id']}_${document['id']}";
					$result[] = $doc;

					$cache = R::dispense('files');
					$cache['url'] = $file;
					$cache['doc'] = $doc;
					R::store($cache);
				} catch(Exception) {
					if(isset($file_name)) {
						unlink("Files/$file_name");
					}

					$result[] = $file;
					continue;
				}
			}

			return implode(',', $result);
		}

		private function generateKeyboard(array $delete_ids, int $conversation_message_id, array $data): string {
			$keyboard = [
				'buttons' => [],
				'inline' => true
			];

			if(count($data[2]) > 5) {
				if($data[1] == 0) {
					$keyboard['buttons'][][] = [
						'action' => [
							'type' => 'callback',
							'label' => 'Далее',
							'payload' => json_encode(['command' => 'get_messages', 'target' => $data[3], 'oc' => $data[0], 'o' => $data[1] + 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
						],
						'color' => 'positive'
					];
				} else {
					if(count(array_slice($data[2], $data[1] + 5, 5)) < 1) {
						$keyboard['buttons'][][] = [
							'action' => [
								'type' => 'callback',
								'label' => 'Назад',
								'payload' => json_encode(['command' => 'get_messages', 'target' => $data[3], 'oc' => $data[0], 'o' => $data[1] - 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
							],
							'color' => 'negative'
						];
					} else {
						$keyboard['buttons'][] = [
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Назад',
									'payload' => json_encode(['command' => 'get_messages', 'target' => $data[3], 'oc' => $data[0], 'o' => $data[1] - 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
								],
								'color' => 'negative'
							],
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Далее',
									'payload' => json_encode(['command' => 'get_messages', 'target' => $data[3], 'oc' => $data[0], 'o' => $data[1] + 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
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
					'label' => 'Список сообщений',
					'payload' => json_encode(['command' => 'eval', 'cmd' => "/messages ${data[0]}", 'update' => $conversation_message_id, 'delete_ids' => $delete_ids])
				],
				'color' => 'secondary'
			];

			return json_encode($keyboard);

		}
	}