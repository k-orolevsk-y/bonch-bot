<?php

	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use Me\Korolevsky\BonchBot\Commands\Order as OrderCmd;

	class Order implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if(!in_array($payload['why'], [0, 1, 2, 3, 4, 5])) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Данные повреждены."])
				]);
				return false;
			}

			$types = OrderCmd::getWhys();
			$vkApi->editMessage("📑 Заказ справок.\n❔ Место предоставления: ${types[$payload['why']]}\n\n✏️ Ответьте на данное сообщение указав цель получения справки.", $object['conversation_message_id'], $object['peer_id'], [
				'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Отмена","payload":"{ \"command\": \"cancel\" }"},"color":"negative"}]],"inline":true}',
				'payload' => json_encode(['action' => 'order', 'why' => $payload['why']])
			]);
			return true;
		}

	}