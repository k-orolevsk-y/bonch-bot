<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use Me\Korolevsky\BonchBot\WebLK;

	class ScreenMarks implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if($payload['update'] == null ) {
				$payload['update'] = $object['conversation_message_id'];
			}

			$vkApi->get("messages.sendMessageEventAnswer", [
				'peer_id' => $object['peer_id'],
				'user_id' => $object['user_id'],
				'event_id' => $object['event_id'],
				'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "ℹ️ Запрос на получение скриншота создан." ])
			]);

			$webLK = new WebLK($object['user_id']);
			$result = $webLK->getScreenMarks();

			$message = $vkApi->useMethod("messages", "getByConversationMessageId", [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => $object['conversation_message_id'] ])['items'][0]['text'];
			if($result['is_error']) {
				$text = match($result['error']) {
					0 => "🔐 Не удалось авторизоваться в ЛК и создать скриншот.\nЭта проблема не связана с вашими данными, такое происходит из-за внутренних конфликтов BonchBot и ЛК.",
					1 => "🔨 ЛК отдал BonchBot неверные данные, с которыми он не может работать, попробуйте позже.",
					default => "🚫 Не удалось создать скриншот оценок, попробуйте позже.",
				};

				$vkApi->editMessage("$message\n\n$text", $payload['update'], $object['peer_id']);
				return false;
			}

			$document = $vkApi->uploadFile($result['response'], $object['peer_id']);
			unlink($result['response']);

			if(!$document) {
				$vkApi->editMessage("$message\n\n🚫 Не удалось загрузить скриншот на сервера ВКонтакте, это временная проблема, попробуйте позже.", $payload['update'], $object['peer_id']);
				return false;
			}

			$vkApi->editMessage("$message\n\n📷 Скриншот ваших оценок:", $payload['update'], $object['peer_id'], [ 'attachment' => $document ]);
			return true;
		}

	}