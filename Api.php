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
				$this->repetitionCheck(0);
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

		protected function repetitionCheck(int $status): bool {
			if($this->object['event_id'] != null) {
				$conversation_message_id = $this->object['event_id'];
			} else {
				$conversation_message_id = $this->object['conversation_message_id'];
			}

			if($status == 0) {
				$find = R::findOne('lm', 'WHERE `conversation_message_id` = ? AND `peer_id` = ? AND `completed` = ?', [$conversation_message_id, $this->object['peer_id'], 1]);
				if($find != null) die('ok');

				$new = R::dispense('lm');
				$new['conversation_message_id'] = $conversation_message_id;
				$new['peer_id'] = $this->object['peer_id'];
				$new['completed'] = 0;
				R::store($new);

				return true;
			}

			$end = R::findOne('lm', 'WHERE `conversation_message_id` = ? AND `peer_id` = ?', [$conversation_message_id, $this->object['peer_id']]);
			if($end == null) return true;

			$end['completed'] = 1;
			R::store($end);

			return true;
		}

		public function sendBonchRequest(string $method, array $params): ?array {
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => "https://bonch.ssapi.ru/$method",
				CURLOPT_POST => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_POSTFIELDS => http_build_query($params)
			]);
			$result = curl_exec($ch);
			curl_close($ch);

			return json_decode($result, true);
		}

		public function getVkApi(string|null $version = null): VKApi {
			if($version != null) {
				return new VKApi($this->access_token, $this->object, $version);
			}

			return $this->vkApi;
		}

		#[NoReturn]
		public function end($fake = false) {
			self::repetitionCheck(1);
			if(!$fake) die('ok');
		}

		#[NoReturn]
		public function exceptionHandler($exception) {
			$this->vkApi->sendMessage("😔 Произошла критическая ошибка.\n🙎🏻‍♂️ [id171812976|Разработчику] уже передана техническая информация.", [
				'attachment' => 'photo-207206992_467239022'
			]);

			$array = explode('/', $exception->getFile());
			$this->vkApi->sendMessage(
				"📛 Бот столкнулся с критической ошибкой: " . $exception->getMessage() . PHP_EOL . "Строчка: " . $exception->getLine() . PHP_EOL . "Файл: " . array_pop($array) . PHP_EOL . "TraceBack: " . $exception->getTraceAsString(),
				[ 'peer_id' => 171812976, 'forward' => [] ]
			);

			die('ok');
		}
	}