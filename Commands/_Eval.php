<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use ParseError;
	use RedBeanPHP\R;

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

			if($msg[1] == null && $object['reply_message'] == null) {
				$vkApi->sendMessage("ℹ️  Правильное использование: /eval [код]\n\n❗️ При отсутствии return в коде, бот автоматически подставит return в начало кода.\nЕсли нет необходимости в возвращении результата, добавьте в конце `return -1;`");
				return true;
			}

			set_exception_handler(function($e) use ($vkApi, $api, $object) {
				$error = str_replace("/var/www/ssapi.ru/bots/bonchbot", "", $e->getMessage());

				$vkApi->sendMessage("❎ При выполнении кода произошла ошибка.\n\nОшибка: " . $error);
				$api->end();
			});

			try {
				$code = implode(' ', array_splice($msg, 1));
				$code = str_replace([ 'self.', 'this.' ],  '$this->', $code);



				$banned_functions = [ 'exec', 'curl', 'echo', 'var_dump', 'print_r', 'print', 'file', 'file_exists', 'file_put_contents', 'file_get_contents', 'eval', 'die', 'exit', 'phpinfo', 'require', 'include', 'require_once', 'include_once', 'while', 'for' ];
				foreach($banned_functions as $banned_function) {
					if(preg_match("~\w*$banned_function\(\w*~", $code) !== 0) {
						$vkApi->sendMessage("❌ В коде нельзя использовать $banned_function().");
						return true;
					}
				}

				$banned_words = [ '_SERVER', '_GET', '_POST', 'HTTP_', 'echo', 'print', 'print_r' ];
				foreach($banned_words as $banned_word) {
					if(preg_match("~\w*$banned_word\w*~", $code) !== 0) {
						$vkApi->sendMessage("❌ В коде нельзя использовать $banned_word.");
						return true;
					}
				}

				if(preg_match("~\w*return\w*~", $code) !== 1) {
					$code = "return $code";
				}


				$execute = @eval($code);
			} catch(ParseError $e) {
				$error = str_replace("/var/www/ssapi.ru/bots/infobot2", "", $e->getMessage());

				$vkApi->sendMessage("❎ При выполнении кода произошла ошибка.\n\nОшибка: " . $error);
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

	}