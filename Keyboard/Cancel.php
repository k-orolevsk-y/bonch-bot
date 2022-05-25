<?php

	namespace Me\Korolevsky\BonchBot\Keyboard;


	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class Cancel implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$api->removeAction();
			return $api->getVkApi()->editMessage("☑️ Операция отменена.", $object['conversation_message_id'], $object['peer_id'], exception_handler_need: false);
		}

	}