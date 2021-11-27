<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	use Me\Korolevsky\BonchBot\Api;

	class Definition {

		public function __construct(Api $api, array $object) {
			$object = (array) $object['message'];
			$vkApi = $api->getVkApi();

			switch($object['action']->type) {
				case "chat_invite_user":
					return new InviteChat($vkApi, $object);
			}
		}

	}