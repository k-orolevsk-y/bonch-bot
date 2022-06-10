<?php
	namespace Me\Korolevsky\BonchBot\Actions;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Action;
	use Me\Korolevsky\BonchBot\Commands\Definition as DefinitionCommands;

	class CommandNeedArguments implements Action {

		public function __construct(Api $api, array $object, array $payload) {
			$msg = explode(' ', $object['text']);
			$object['text'] = "${payload['command']} ${msg[0]}";

			$api->removeAction();
			return new DefinitionCommands($api, $object);
		}

	}