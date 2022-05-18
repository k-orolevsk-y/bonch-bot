<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use RedBeanPHP\R;

	class BugFix implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			$peer_ids_logs = json_decode(R::findOne('settings', 'WHERE `name` = ?', [ 'chats_logs' ])['value'], true);
			if(!in_array($object['peer_id'], $peer_ids_logs)) {
				$vkApi->sendMessage("🥶 Ну привет хакерочек, зачем через dev.vk.com бота насилуешь?", [ 'forward' => [] ]);
				return false;
			}

			$vkApi->sendMessage("⚡️ ".date('d.m.Y в H:i', $payload['time'])." при использовании бота вы столкнулись с неизвестной нам ошибкой. Сожалеем, что такое произошло с вами.\n\n✅ Только что данная ошибка была исправлена. Мы надеемся, что у вас не сложились плохие впечатления из-за данного недоразумения.\n\n☀️ Желаем приятной работы с ботом!", [
				'forward' => [],
				'peer_id' => $payload['user_id'],
			]);
			$vkApi->get("messages.sendMessageEventAnswer", [
				'peer_id' => $object['peer_id'],
				'user_id' => $object['user_id'],
				'event_id' => $object['event_id'],
				'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "✅ Сообщение об успешном исправлении ошибки направлено пользователю." ])
			]);

			$message = $vkApi->useMethod("messages", "getByConversationMessageId", [ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => $object['conversation_message_id'] ])['items'][0];
			if($message['attachments'][0]['type'] == 'link') {
				$attachment = $message['attachments'][0]['link']['url'];
			} elseif($message['attachments'][0]['type'] == "doc") {
				$document = $message['attachments'][0]['doc'];
				$attachment = "doc${document['owner_id']}_${document['id']}";
			} else {
				$attachment = "";
			}
			$text = $message['text'];

			$vkApi->editMessage("✅ ОШИБКА ИСПРАВЛЕНА. Отметка об исправлении поставлена пользователем ".$vkApi->getName($object['user_id']).".\n\n".$text, $object['conversation_message_id'], $object['peer_id'], [ 'attachment' => $attachment, 'keep_forward_messages' => 1 ]);
			return true;
		}

	}