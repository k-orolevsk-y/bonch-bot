<?php

	namespace Me\Korolevsky\BonchBot\Actions;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Action;
	use Me\Korolevsky\BonchBot\Commands\Order as OrderCmd;

	class Order implements Action {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();
			$vkApi->get("messages.delete", ['peer_id' => $object['peer_id'], 'conversation_message_ids' => [$payload['reply_message_id']], 'delete_for_all' => 1]);

			if(!in_array($payload['why'], [0, 1, 2, 3, 4, 5])) {
				$vkApi->editMessage("📛 Данные повреждены.", $object['conversation_message_id'], $object['peer_id'], []);
				return false;
			}

			$types = OrderCmd::getWhys();
			if(iconv_strlen($object['text']) > 150) {
				$vkApi->editMessage("⚠️ Цель получения не может быть больше 150 символов.\n\n📑 Заказ справок.\n❔ Место предоставления: ${types[$payload['why']]}\n\n✏️ Ответьте на данное сообщение указав цель получения справки.", $object['conversation_message_id'], $object['peer_id'], [
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Отмена","payload":"{ \"command\": \"cancel\" }"},"color":"negative"}]],"inline":true}',
					'payload' => json_encode(['action' => 'order', 'why' => $payload['why']])
				]);
				return false;
			}

			$vkApi->sendMessage("📑 Сформирован запрос на заказ справки.\n\n❔ Место предоставления: ${types[$payload['why']]}\n✏️ Цель получения: ${object['text']}\n\n⚙️ Выполнить заказ справки?", [
				'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Заказать","payload":"{ \"command\": \"order_confirm\", \"why\": ' . $payload['why'] . ', \"goal\": \"' . $object['text'] . '\" }"},"color":"positive"},{"action":{"type":"callback","label":"Отмена","payload":"{ \"command\": \"cancel\" }"},"color":"negative"}]],"inline":true}'
			]);
			return true;
		}

	}