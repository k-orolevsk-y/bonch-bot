<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	require '../Autoload.php';
	error_reporting(0);

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class NewMessages {

		private Api $api;

		private int $start_time;
		private array $logs;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");
			$this->start_time = microtime(true);

			self::getApi();
			self::start();
		}

		#[NoReturn]
		public function __destruct() {
			if(count($this->logs) > 2) {
				$peer_ids = json_decode(R::findOne('settings', 'WHERE `name` = ?', [ 'chats_logs' ])['value'], true);

				$path = '../Files/'.date('d.m.Y-H:i:s').'-bonchbot-nm.log';
				file_put_contents($path, var_export($this->logs, true));

				$doc = $this->api->getVkApi()->uploadFile($path, 171812976);
				unlink($path);

				if(!$doc) { // Әгәр лог барлыкка килмәгән бу вк, ягъни без җибәрмибез хәбәр өчен түгел, сакларга, аны сервере.
					return;
				}

				$this->api->getVkApi()->sendMessage(
					"⚙️ Обработчик проверки новых сообщений завершил работу (".round(microtime(true)-$this->start_time, 3)." сек.) и прислал лог-файл, он прикреплён к сообщению.\n\n#handler_new_messages",
					[
						'forward' => [],
						'attachment' => $doc,
						'peer_ids' => $peer_ids,
					]
				);
			}
		}

		#[NoReturn]
		private function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
			$this->logs[] = date('[d.m.Y H:i:s]')." Создан экземпляр класса API.";
		}

		#[NoReturn]
		private function start() {
			$users = R::getAll('SELECT * FROM `users`');
			$this->logs[] = date('[d.m.Y H:i:s]')." Получен список пользователей (".count($users)."), начинаю проверку каждого.";

			foreach($users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['new_messages']) continue;

				$lk = new LK($user['user_id']);
				$auth = $lk->auth();
				if($auth != 1) {
					continue;
				}

				$new_messages = $lk->getNewMessages();
				$group_id = Data::GROUP_ID;
				if($new_messages != null) {
					$this->logs[] = [
						'text' => date('[d.m.Y H:i:s]')." Пользователю ${user['user_id']} пришли новые сообщения, отправляю уведомления об этом.",
						'obj' => $new_messages
					];

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

				$new_files_group = $lk->getNewFilesGroup();
				if($new_files_group != null) {
					$this->logs[] = [
						'text' => date('[d.m.Y H:i:s]')." Пользователю ${user['user_id']} пришли новые сообщения в ФАЙЛЫ ГРУППЫ, отправляю уведомления об этом.",
						'obj' => $new_files_group
					];

					if(count($new_files_group) > 1) {
						$this->api->getVkApi()->sendMessage("🔔 Вам пришли новые сообщения в ФАЙЛЫ ГРУППЫ! (" . $this->api->pluralForm(count($new_files_group), ['штука', 'штуки', 'штук']) . ")", ['peer_id' => $user['user_id'], 'forward' => []]);

						foreach($new_files_group as $message) {
							$files = self::getFiles($message['files'], (int) $user['user_id']);
							$this->api->getVkApi()->sendMessage("🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
						}
					} else {
						$message = $new_files_group[0];
						$files = self::getFiles($message['files'], (int) $user['user_id']);

						if($message['sender'] == "Старостин Владимир Сергеевич") {
							$this->api->getVkApi()->sendMessage("😈 Приспешник дьявола соизволил отправить вам телеграмму в ФАЙЛЫ ГРУППЫ!\n\n🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
						} else {
							$this->api->getVkApi()->sendMessage("🔔 Вам пришло новое сообщение в ФАЙЛЫ ГРУППЫ!\n\n🙇🏻 Отправитель: [club$group_id|${message['sender']}]\n⏱ Время: " . date('d.m.Y H:i:s', $message['time']) . "\n📑 Тема: [club$group_id|${message['title']}]\n✏️ Текст: " . ($message['text'] ?? "Без текста"), ['peer_id' => $user['user_id'], 'forward' => [], 'attachment' => $files]);
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
						throw new \Exception(code: 0);
					}
					$uploaded_doc = $this->api->getVkApi()->getClient()->getRequest()->upload($address, 'file', "../Files/${file_name}")['file'];
					if($uploaded_doc == null) {
						throw new \Exception(code: 1);
					}
					$document = $this->api->getVkApi()->get("docs.save", ['file' => $uploaded_doc, 'title' => "Файл `$file_name` из ЛК для пользователя $user_id"])['response']['doc'];
					if($document == null) {
						throw new \Exception(code: 1);
					}

					unlink("../Files/$file_name");

					$result[] = "doc${document['owner_id']}_${document['id']}";
					$this->logs[] = [
						'text' => date('[d.m.Y H:i:s]')." Успешно загружен файл для пользователя $user_id.",
						'file' => $file,
						'doc' => "doc${document['owner_id']}_${document['id']}"
					];
				} catch(\Exception $e) {
					if(isset($file_name)) {
						unlink("../Files/$file_name");
					}

					$this->logs[] = [
						'text' => date('[d.m.Y H:i:s]')." Не удалось загрузить файл для пользователя $user_id.",
						'file' => $file,
						'error' => var_export($e, true)
					];
					continue;
				}
			}

			return implode(',', $result);
		}
	}

	new NewMessages();