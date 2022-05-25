<?php
	namespace Me\Korolevsky\BonchBot;
	require 'Autoload.php';

	use JetBrains\PhpStorm\NoReturn;
	use Me\Korolevsky\BonchBot\Handlers\NewPost;
	use Me\Korolevsky\BonchBot\Commands\Definition;
	use VK\CallbackApi\Server\VKCallbackApiServerHandler;
	use Me\Korolevsky\BonchBot\Actions\Definition as DefinitionActions;
	use Me\Korolevsky\BonchBot\Handlers\Definition as DefinitionHandlers;
	use Me\Korolevsky\BonchBot\Keyboard\Definition as DefinitionKeyboard;

	class Core extends VKCallbackApiServerHandler {

		#[NoReturn]
		public function confirmation(int $group_id, ?string $secret) {
			if($group_id !== Data::GROUP_ID || $secret !== Data::SECRET) {
				die("401 Authorization failed.");
			}

			die(Data::CONFIRM_KEY);
		}

		#[NoReturn]
		public function wallPostNew(int $group_id, ?string $secret, array $object) {
			if($group_id !== Data::GROUP_ID || $secret !== Data::SECRET) {
				die("401 Authorization failed.");
			}

			$object['peer_id'] = -1;
			$object['conversation_message_id'] = $object['id'];

			$api = new Api(Data::TOKENS['public'], $object);
			

			new NewPost($api->getVkApi(), $object);
			$api->end();
		}

		#[NoReturn]
		public function messageEvent(int $group_id, ?string $secret, array $object) {
			if($group_id !== Data::GROUP_ID || $secret !== Data::SECRET) {
				die("401 Authorization failed.");
			}

			$api = new Api(Data::TOKENS['public'], $object);
			$payload = (array) $object['payload'];

			new DefinitionKeyboard($api, $object, $payload);
			$api->end();
		}

		#[NoReturn]
		public function messageNew(int $group_id, ?string $secret, array $object) {
			if($group_id !== Data::GROUP_ID || $secret !== Data::SECRET) {
				die("401 Authorization failed.");
			}

			$obj = (array) $object['message'];
			if($obj['from_id'] < 0) die('ok'); // Отключаем работу от имени групп

			$api = new Api(Data::TOKENS['public'], $obj);
			$payload = json_decode($obj['payload'], true);

			new DefinitionHandlers($api, $object);
			if($payload != null) {
				new DefinitionKeyboard($api, $object, $payload);
				$api->end();
			}

			$payload_reply_message = json_decode(((array)$obj['reply_message'])['payload'], true);
			$payload_action = $api->getAction();

			if($payload_reply_message['action'] != null || $payload_action) {
				if($payload_reply_message == null) {
					$payload_reply_message = $payload_action;
				} else {
					$payload_reply_message['reply_message_id'] = $obj['reply_message']->conversation_message_id;
				}

				new DefinitionActions($api, $obj, $payload_reply_message);

				$api->removeAction();
				$api->end();
			}

			$msg = explode(' ', $obj['text']);
			if($obj['peer_id'] > 2000000000) {
				preg_match('/\[club(.*)\|.*\]/', $msg[0], $matches);
				if(intval($matches[1]) == $group_id) {
					$obj['text'] = implode(' ', array_slice($msg, 1));
				}
			}

			new Definition($api, $obj);
			$api->end();
		}

	}

	$data = json_decode(file_get_contents('php://input'));
	if($data == null) {
		die("401 Authorization failed.");
	}

	$handler = new Core();
	$handler->parse($data);