<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Commands\Definition as DefinitionCommands;

	class Definition {

		public function __construct(Api $api, array $object, array $payload) {
			if($payload['command'] == null) die('ok');

			if($object['message'] != null) {
				$object = (array) $object['message'];
			}

			if($payload['for'] != null) {
				if($payload['for'] != ($object['from_id'] ?? $object['user_id'])) {
					if($object['event_id'] != null) {
						$api->getVkApi()->get("messages.sendMessageEventAnswer", [
							'peer_id' => $object['peer_id'],
							'user_id' => $object['user_id'],
							'event_id' => $object['event_id'],
							'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "👮🏼‍♂️ Вы не можете использовать данную кнопку." ])
						]);
					}
					return false;
				}
			}

			switch($payload['command']) {
				case "eval":
					$object['text'] = $payload['cmd'];
					return new DefinitionCommands($api, $object);
				case "set_mark":
					return new SetMark($api, $object, $payload);
				case "del_mark":
					return new DelMark($api, $object, $payload);
				case "settings":
					return new Settings($api, $object, $payload);
				case "set_settings":
					return new SetSettings($api, $object, $payload);
				case "start":
					return new Start($api, $object, $payload);
			}
		}

	}