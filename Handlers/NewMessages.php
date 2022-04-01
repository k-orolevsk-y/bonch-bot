<?php

	namespace Me\Korolevsky\BonchBot\Handlers;
	error_reporting(0);

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class NewMessages {

		private Api $api;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::autoload();
			self::getApi();
			self::start();
		}

		#[NoReturn]
		private function autoload() {
			require '../LK.php';
			require '../Api.php';
			require '../Data.php';
			require '../VKApi.php';
			require '../WebLK.php';
			require '../vendor/autoload.php';
		}

		#[NoReturn]
		private function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
		}

		#[NoReturn]
		private function start() {
			$users = R::getAll('SELECT * FROM `users`');
			foreach($users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['new_messages']) continue;

				$lk = new LK($user['user_id']);
				$auth = $lk->auth();
				if($auth != 1) continue;


				$new_messages = $lk->getNewMessages();
				$group_id = Data::GROUP_ID;
				if($new_messages != null) {
					if(count($new_messages) > 1) {
						$this->api->getVkApi()->sendMessage("🔔 Вам пришли новые сообщения в ЛК! (" . $this->api->pluralForm(count($new_messages), ['штука', 'штуки', 'штук']) . ")", ['peer_id' => $user['user_id'], 'forward' => []]);

						foreach($new_messages as $message) {
							$files = self::getFiles($message['files'], (int) $user['user_id']);
							$this->api->getVkApi()->sendMessage("🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
						}
					} else {
						$message = $new_messages[0];
						$files = self::getFiles($message['files'], (int) $user['user_id']);

						if($message['sender'] == "Старостин Владимир Сергеевич") {
							$this->api->getVkApi()->sendMessage("😈 Приспешник дьявола соизволил отправить вам телеграмму в ЛК!\n\n🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
						} else {
							$this->api->getVkApi()->sendMessage("🔔 Вам пришло новое сообщение в ЛК!\n\n🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
						}
					}
				}
			}
		}

		private function getFiles(array $files, int $user_id): string {
			$result = [];
			foreach($files as $file) {
				try {
					$file_name = basename($file);
					file_put_contents("../Files/$file_name", file_get_contents($file));

					$address = $this->api->getVkApi()->get("docs.getMessagesUploadServer", ['peer_id' => $user_id, 'type' => 'doc'])['response']['upload_url'];
					if($address == null) {
						throw new Exception(code: 0);
					}
					$uploaded_doc = $this->api->getVkApi()->getClient()->getRequest()->upload($address, 'file', "../Files/${file_name}")['file'];
					if($uploaded_doc == null) {
						throw new Exception(code: 1);
					}
					$document = $this->api->getVkApi()->get("docs.save", ['file' => $uploaded_doc, 'title' => "Файл `$file_name` из ЛК для пользователя $user_id"])['response']['doc'];
					if($document == null) {
						throw new Exception(code: 1);
					}

					unlink("../Files/$file_name");
					$result[] = "doc${document['owner_id']}_${document['id']}";
				} catch(\Exception) {
					if(isset($file_name)) {
						unlink("../Files/$file_name");
					}
					continue;
				}
			}

			return implode(',', $result);
		}
	}

	new NewMessages();