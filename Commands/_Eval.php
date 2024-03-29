<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Exception;
	use ParseError;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\WebLK;

	class _Eval {

		private Api $api;
		private array $object;

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', str_replace("\n", " ", $object['text']));

			$object_from_api = $vkApi->useMethod("messages", "getByConversationMessageId",
				[ 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ]
			)['items'][0];
			if($object_from_api['date'] != $object['date'] || $object_from_api['from_id'] != $object['from_id'] || $object['text'] != $object_from_api['text']) {
				if($object['peer_id'] <= 2000000000) {
					$vkApi->sendMessage("⛔️ Неизвестная команда.\nℹ️ Запросите справку командой: /help");
				}
				return false;
			}

			if($object['from_id'] != 171812976) {
				if($object['peer_id'] <= 2000000000) {
					$vkApi->sendMessage("⛔️ Неизвестная команда.\nℹ️ Запросите справку командой: /help");
				}
				return false;
			}

			$this->api = $api;
			$this->object = $object;

			if($msg[1] == null) {
				$vkApi->sendMessage("ℹ️  Правильное использование: /eval [код]\n\n❗️ При отсутствии return в коде, бот автоматически подставит return в начало кода.\nЕсли нет необходимости в возвращении результата, добавьте в конце `return -1;`");
				return true;
			} elseif($object['reply_message'] != null && $api->cM($msg[1], [ 'repeat', 'repeat;' ])) {
				$object['text'] = $object['reply_message']->text;
				$msg = explode(' ', str_replace("\n", " ", $object['text']));

				$this->object = $object;
			}

			set_exception_handler(function($e) use ($vkApi, $api, $object) {
				$vkApi->sendMessage("❎ При выполнении кода произошла ошибка.\n\nОшибка: " . var_export($e, true));
				$api->end();
			});

			try {
				$code = implode(' ', array_splice($msg, 1));
				$code = str_replace([ 'self.', 'this.' ],  '$this->', $code);

				if(preg_match("~\w*return\w*~", $code) !== 1) {
					$code = "return $code";
				}


				$execute = @eval($code);
			} catch(ParseError $e) {
				$vkApi->sendMessage("❎ При выполнении кода произошла ошибка.\n\nОшибка: " . var_export($e, true));
				return true;
			}

			$result = is_string($execute) ? $execute : var_export($execute, true);
			if(intval($result) == -1) {
				return true;
			} elseif(iconv_strlen($result) > 4000) {
				$vkApi->sendMessage("✏️ Часть результата:");
				$vkApi->sendMessage(mb_strcut($result, 0, 4000));
				return true;
			}

			if(str_replace(" ", "", $result) == "") $result = "null";
			$vkApi->sendMessage("✏️ Результат:\n$result");

			return true;
		}

		private function sendMessage($text, $params = []): int {
			if($params['forward'] == null) {
				$params['forward'] = [];
			}
			$this->api->getVkApi()->sendMessage($text, $params);

			return -1;
		}

		private function setLogsChat(): string {
			$settings = R::findOne('settings', 'WHERE `name` = ?', [ 'chats_logs' ]);
			$value = json_decode($settings['value'], true);

			$in_array = in_array($this->object['peer_id'], $value);
			if($in_array) {
				unset($value[array_search($this->object['peer_id'], $value)]);
			} else {
				$value[] = $this->object['peer_id'];
			}

			$settings['value'] = json_encode($value);
			R::store($settings);

			return $in_array ? "Данный чат был удалён из системы логов." : "Данный чат добавлен в систему логов.\n\nОбращаю ваше внимание на то, что в данном чате бот будет отправлять всевозможную информацию о себе, не советую использовать эту функцию в личных сообщениях!";
		}

		/**
		 * @throws Exception
		 */
		private function getLK(int $user_id = 0): LK {
			if($user_id == 0) {
				$user_id = $this->object['from_id'];
			}

			$lk = new LK($user_id);
			$auth = $lk->auth();
			if($auth != 1) {
				throw new Exception("LK Pidoras: $auth");
			}

			return $lk;
		}

		private function getWebLK(int $user_id = 0, string $login = null, string $password = null): WebLK {
			if(isset($login) && isset($password)) {
				return new WebLK(null, $login, $password);
			}

			if($user_id == 0) {
				$user_id = $this->object['from_id'];
			}

			return new WebLK($user_id);
		}

		public function getUser(int $user_id): ?array {
			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $user_id ]);
			if($user_id == null) {
				return null;
			}

			return [
				'db_id' => intval($user['id']),
				'user_id' => intval($user['user_id']),
				'group_id' => intval($user['group_id']),
				'data' => json_decode($user['data'], true),
				'settings' => json_decode($user['settings'], true)
			];
		}

		public function clearCookie(int $user_id = null): bool {
			if($user_id == null) {
				$users = R::getAll('SELECT * FROM `users`');
				foreach($users as $user) {
					$user = R::findOne('users', 'WHERE `id` = ?', [ $user['id'] ]);
					$user['cookie'] = null;
					R::store($user);
				}

			} else {
				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $user_id ]);
				if($user == null) {
					return false;
				}

				$user['cookie'] = null;
				R::store($user);

			}

			return true;
		}

	}