<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Exception;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Command;
	use Me\Korolevsky\BonchBot\WebLK;
	use RedBeanPHP\R;

	class Marks implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$payload = (array) $object['payload'];

			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("⚙️ Информация отправлена Вам в личные сообщения.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);

				$forward = [];
				$object['peer_id'] = $object['from_id'];
			} else {
				$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($user == null) {
				$vkApi->sendMessage("📛 У Вас не привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"secondary"}]],"inline":true}',
					'peer_id' => $object['peer_id'],
					'forward' => $forward
				]);
				return false;
			}

			if($payload['update'] == null) {
				$conversation_message_id = $vkApi->sendMessage("📘 Получаю скриншот из ЛК...", [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward
					]
				)[0]['conversation_message_id'];
			} else {
				$conversation_message_id = $payload['update'];
				$vkApi->editMessage("📘 Получаю скриншот из ЛК...", $conversation_message_id, $object['peer_id']);
			}

			$webLK = new WebLK(intval($object['from_id']));
			$marks = $webLK->getScreenMarks();

			if($marks == null) {
				$vkApi->editMessage("❌ Не удалось создать скриншот оценок.", $conversation_message_id, $object['peer_id']);
				return false;
			}

			try {
				$address = $vkApi->get("docs.getMessagesUploadServer", ['peer_id' => $object['peer_id'], 'type' => 'doc'])['response']['upload_url'];
				if($address == null) {
					throw new Exception(code: 0);
				}
				$uploaded_doc = $vkApi->getClient()->getRequest()->upload($address, 'file', $marks)['file'];
				if($uploaded_doc == null) {
					throw new Exception(code: 1);
				}
				$document = $vkApi->get("docs.save", ['file' => $uploaded_doc, 'title' => "Оценки пользователя ${object['from_id']} от " . date('d.m.Y H:i')])['response']['doc'];
				if($document == null) {
					throw new Exception(code: 1);
				}
			} catch(Exception $e) {
				unlink($marks);
				if($e->getCode() == 0) {
					$vkApi->editMessage("📛 Невозможно загрузить скриншот, скорее всего у бота закрыт доступ к отправки для вас сообщений.", $conversation_message_id, $object['peer_id']);
				} else {
					$vkApi->editMessage("📛 Не удалось загрузить скриншот оценок.", $conversation_message_id, $object['peer_id']);
				}
				return false;
			}

			unlink("Files/${data['file_name']}");
			$vkApi->editMessage("🎓 Ваши оценки:", $conversation_message_id, $object['peer_id'], [ 'attachment' => "doc${document['owner_id']}_${document['id']}", 'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Обновить","payload":"{ \"command\": \"eval\", \"cmd\": \"/marks\", \"update\": '.$conversation_message_id.' }"},"color":"secondary"}]],"inline":true}' ]);
			return true;
		}

	}