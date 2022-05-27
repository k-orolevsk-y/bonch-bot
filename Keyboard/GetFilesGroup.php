<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Exception;
	use Me\Korolevsky\BonchBot\Data;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class GetFilesGroup implements Keyboard {

		private Api $api;

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();
			$this->api = $api;

			if($payload['d'] != null) {
				if(!in_array($object['conversation_message_id'], $payload['d'])) {
					$payload['d'][] = $object['conversation_message_id'];
				}

				$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => $payload['d'], 'delete_for_all' => 1]);
			}

			if($payload['u'] != null) {
				$object['conversation_message_id'] = $payload['u'];
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

			$lk = new LK($object['user_id']);
			if($lk->auth() != 1) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => '⚠️ Авторизоваться в ЛК не удалось, попробуйте ещё раз.' ])
				]);
				return false;
			}
			$object['conversation_message_id'] = $vkApi->editMessage('✏️ Формируем список сообщений.', $object['conversation_message_id'], $object['peer_id']);

			$files_group = $lk->getFilesGroup();
			$slice_files_group = array_slice($files_group, 0+$payload['o'], 5);
			$result = [];

			foreach($slice_files_group as $message) {
				$group_id = Data::GROUP_ID;

				$result[] = [
					'text' => "🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] == null ? "Без текста" : $message['text']),
					'attachment' => self::getFiles($message['files'], $object['user_id'])
				];
			}

			$ids = [];
			$object['conversation_message_id'] = $vkApi->editMessage("💬 " . $api->pluralForm(count($slice_files_group), ["сообщение отправлено", "сообщения отправлены", "сообщений отправлено"]) . " из файлов группы. " . ($payload['o'] > 1 && count($files_group) > 5 ? "(Отступ ${payload['o']})" : ""), $object['conversation_message_id'], $object['peer_id']);

			foreach($result as $message) {
				$ids[] = $vkApi->sendMessage($message['text'], ['attachment' => $message['attachment'], 'forward' => [], 'peer_ids' => $object['peer_id']])[0]['conversation_message_id'];;
			}
			$vkApi->sendMessage("ℹ️ Выберите дальнейшей действие:", [ 'forward' => [], 'keyboard' => self::generateKeyboard($ids, $object['conversation_message_id'], [$payload['oc'] ?? 0, $payload['o'] ?? 0, $files_group]) ]);

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
					$document = $this->api->getVkApi()->get("docs.save", ['file' => $uploaded_doc, 'title' => "Файл `$file_name` из ЛК (файлы группы)"])['response']['doc'];
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
							'payload' => json_encode(['command' => 'get_files_group', 'oc' => $data[0], 'o' => $data[1] + 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
						],
						'color' => 'positive'
					];
				} else {
					if(count(array_slice($data[2], $data[1] + 5, 5)) < 1) {
						$keyboard['buttons'][][] = [
							'action' => [
								'type' => 'callback',
								'label' => 'Назад',
								'payload' => json_encode(['command' => 'get_files_group', 'oc' => $data[0], 'o' => $data[1] - 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
							],
							'color' => 'negative'
						];
					} else {
						$keyboard['buttons'][] = [
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Назад',
									'payload' => json_encode(['command' => 'get_files_group', 'oc' => $data[0], 'o' => $data[1] - 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
								],
								'color' => 'negative'
							],
							[
								'action' => [
									'type' => 'callback',
									'label' => 'Далее',
									'payload' => json_encode(['command' => 'get_files_group', 'oc' => $data[0], 'o' => $data[1] + 5, 'd' => $delete_ids, 'u' => $conversation_message_id])
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