<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class DefaultCmd implements Command {

		public function __construct(Api $api, array $object) {
			if($object['peer_id'] > 2000000000) return false;

			$api->getVkApi()->sendMessage("⛔️ Неизвестная команда.", [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Справка","payload":"{ \"command\": \"eval\", \"cmd\": \"/help\" }"},"color":"secondary"}]],"inline":true}'
			]);
			return true;
		}

	}