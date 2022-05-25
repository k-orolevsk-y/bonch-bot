<?php
	namespace Me\Korolevsky\BonchBot;

	use RedBeanPHP\R;
	use JetBrains\PhpStorm\NoReturn;

	class Api {

		private VKApi $vkApi;
		private string $access_token;
		private array $object;

		public function __construct(string $access_token, array $object, bool $need_repetition = true) {
			$this->connectDataBase();
			set_exception_handler([ $this, 'exceptionHandler' ]);

			$this->vkApi = new VKApi($access_token, $object);
			$this->access_token = $access_token;
			$this->object = $object;

			if($need_repetition) {
				$this->end(true);
			}
		}

		public function connectDataBase() {
			$db = Data::DB_INFO;
			try {
				R::setup("mysql:host=${db['host']};dbname=${db['dbname']}", $db['user'], $db['pass']);
			} catch(\Exception $e) {
				die(
					"Бот столкнулся с критической ошибкой при соединении с базой данных: " . $e->getMessage()
				);
			}
		}

		public function cM(?string $msg1, $msg2): bool {
			if($msg1 == null || $msg2 == null) return false;

			if(is_string($msg2)) {
				if(mb_strtolower($msg1) === mb_strtolower($msg2)) return true;

				return false;
			}

			$msg2 = array_map('mb_strtolower', $msg2);
			if(in_array(mb_strtolower($msg1), $msg2)) return true;

			return false;
		}

		public function pluralForm($number, array $after): string {
			$num = $number;
			if(strstr($number, '.') !== false) $number = explode('.', $number)[1];

			$cases = array (2, 0, 1, 1, 1, 2);
			return $num.' '.$after[ ($number%100>4 && $number%100<20)? 2: $cases[min($number%10, 5)] ];
		}

		public function createAction(array $payload): bool {
			try {
				$action = R::dispense('actions');
				$action['user_id'] = $this->object['from_id'] ?? $this->object['user_id'];
				$action['peer_id'] = $this->object['peer_id'];
				$action['payload'] = json_encode($payload);
				R::store($action);
			} catch(\Exception) {
				return false;
			}

			return true;
		}

		public function removeAction(int $action_id = 0): bool {
			if($action_id == 0) {
				$actions = R::getAll('SELECT * FROM `actions` WHERE `user_id` = ?', [ $this->object['from_id'] ?? $this->object['user_id'] ]);
				if($actions == null) {
					return false;
				}

				R::trashAll(R::convertToBeans('actions', $actions));
			} else {
				$action = R::findOne('actions', 'WHERE `id` = ?', [ $action_id ]);
				if($action == null) {
					return false;
				}

				R::trash($action);
			}

			return true;
		}

		public function getAction(): ?array {
			$action = R::findOne('actions', 'WHERE `user_id` = ? AND `peer_id` = ?', [ $this->object['from_id'] ?? $this->object['user_id'], $this->object['peer_id'] ]);
			if($action == null) {
				return null;
			}

			return json_decode($action['payload'], true);
		}

		public function getVkApi(string|null $version = null): VKApi {
			if($version != null) {
				return new VKApi($this->access_token, $this->object, $version);
			}

			return $this->vkApi;
		}

		#[NoReturn]
		public function end($fake = false) {
			if($fake) {
				ob_start(); // Отдаем HTTP ответ серверу VK и продолжаем работу скрипта.
				session_start();

				echo 'ok';

				session_write_close();
				set_time_limit(0);
				ignore_user_abort(true);
				header('Connection: close');
				header('Content-Length: ' . ob_get_length());
				ob_end_flush();
				flush();
				fastcgi_finish_request();
			} else {
				die();
			}
		}

		#[NoReturn]
		public function exceptionHandler($exception) {
			$object = $this->object;
			if(!empty($object['peer_id'])) {
				$this->vkApi->sendMessage("⚠️ При обработке команды произошла ошибка.\nЯ направил сообщение о данной ошибке необходимым людям, они исправят её в скором времени.\n\n⚡️ После исправления ошибки вы получите об этом уведомление в личные сообщения.", [
					'attachment' => 'photo-207206992_467239022',
					'forward' => []
				]);
			}

			$file = explode('/', $exception->getFile());
			$peer_ids = json_decode(R::findOne('settings', 'WHERE `name` = ?', [ 'chats_logs' ])['value'], true);

			$path = 'Files/'.date('d.m.Y-H:i:s').'-bonchbot-error.log';
			file_put_contents(__DIR__.'/'.$path, var_export($exception, true));

			$doc = $this->vkApi->uploadFile(__DIR__.'/'.$path, 171812976);
			if(!$doc) {
				$doc = "https://ssapi.ru/bots/bonch/".$path;
			} else {
				unlink(__DIR__.'/'.$path);
			}

			if(!empty($object['peer_id'])) {
				$this->removeAction();
				$this->vkApi->sendMessage(
					"⚠️ При обработке команды произошла техническая ошибка, информация о ней:\n\nВремя: ".date('d.m.Y H:i:s') ."\nФайл: ".end($file)."\nID чата: ${object['peer_id']}\nID сообщения/эвента: ".($object['event_id'] ?? $object['conversation_message_id']) . "\nПолезная нагрузка: " . (isset($object['payload']) ? json_encode($object['payload']) : "NULL") . "\n\nФайл-лог ошибки, предоставлен вместе с сообщением.",
					[
						'forward' => [],
						'attachment' => $doc,
						'peer_ids' => implode(',', $peer_ids),
						'keyboard' => '{"inline":true,"buttons":[[{"action":{"type":"callback","label":"Ошибка исправлена","payload":"{ \"command\": \"bugfix\", \"user_id\": '.($object['user_id'] ?? $object['from_id']).', \"time\": '.time().' }"},"color":"positive"}]]}'
					]
				);
			} else {
				$this->vkApi->sendMessage(
					"⚠️ При работе обработчика произошла техническая ошибка, информация о ней:\n\nВремя: ".date('d.m.Y H:i:s') ."\nФайл: ".end($file) . "\n\nФайл-лог ошибки, предоставлен вместе с сообщением.",
					[
						'forward' => [],
						'attachment' => $doc,
						'peer_ids' => implode(',', $peer_ids),
					]
				);
			}

			die('ok');
		}
	}