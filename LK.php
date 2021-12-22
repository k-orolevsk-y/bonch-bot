<?php
	namespace Me\Korolevsky\BonchBot;

	use DOMXPath;
	use Exception;
	use DOMDocument;
	use RedBeanPHP\R;
	use JetBrains\PhpStorm\ArrayShape;

	class LK {

		private int $user_id;
		private string $cookie;

		public function __construct(int $user_id) {
			$this->user_id = $user_id;
		}

		public function auth(): int {
			$user = R::findOne('users', 'WHERE `user_id` = ?', [$this->user_id]);
			if($user == null) {
				return -1;
			} elseif(date('H') > 2 && date('H') < 3) { // ЛК ложиться в это время, отключаем работу чтобы не было ложных ошибок.
				return -2;
			}

			$request = self::request("news", cookie: openssl_decrypt(hex2bin($user['cookie']), 'AES-128-CBC', Data::ENCRYPT_KEY) ?? "");
			if($request === false) {
				$login = openssl_decrypt(hex2bin($user['login']), 'AES-128-CBC', Data::ENCRYPT_KEY);
				$pass = openssl_decrypt(hex2bin($user['password']), 'AES-128-CBC', Data::ENCRYPT_KEY);

				// Путь прописан напрямую, поскольку этот класс может вызываться в разных частях бота (т.е. это может быть внутренней папкой и из-за этого не получиться запустить GetCookie)
				$cookie = exec("python3.9 /var/www/ssapi.ru/bots/bonch/Python/GetCookie.py '$login' '$pass'");
				if(!is_string($cookie)) {
					return -2;
				}

				$user['cookie'] = bin2hex(openssl_encrypt($cookie, 'AES-128-CBC', Data::ENCRYPT_KEY));
				R::store($user);
			}

			$this->cookie = openssl_decrypt(hex2bin($user['cookie']), 'AES-128-CBC', Data::ENCRYPT_KEY);
			return 1;
		}

		public function request(string $method, array $params = [], string $cookie = null): string|null|int|false {
			if($cookie === null) {
				$cookie = $this->cookie;
			}

			$ch = curl_init("https://lk.sut.ru/cabinet/project/cabinet/forms/${method}.php?" . http_build_query($params));
			curl_setopt_array($ch, [
				CURLOPT_TIMEOUT => 5,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_HTTPHEADER => [
					'Host: lk.sut.ru',
					'Accept-Language: ru',
					"Cookie: miden=$cookie;",
					'Connection: keep-alive',
					'Accept: text/html, */*; q=0.01',
					'X-Requested-With: XMLHttpRequest',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://lk.sut.ru/cabinet/?login=yes',
					'Content-Type: application/x-www-form-urlencoded;',
					'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.1 Safari/605.1.15',
				],
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			]);
			$result = iconv("Windows-1251", "UTF-8", curl_exec($ch));
			curl_close($ch);

			if($error = curl_errno($ch)) {
				if($error == 23) { // Если ты не авторизован, ЛК может прислать 23 код ошибки (curl error)
					return false;
				}
				return $error;
			} elseif($result == "У Вас нет прав доступа. Или необходимо перезагрузить приложение..") {
				return false;
			} elseif($result === false) {
				$result = null;
			}

			return $result;
		}

		public function post(string $method, array $params = [], string $cookie = null): string|null|int|false {
			if($cookie === null) {
				$cookie = $this->cookie;
			}

			$ch = curl_init("https://lk.sut.ru/cabinet/project/cabinet/forms/${method}.php");
			curl_setopt_array($ch, [
				CURLOPT_TIMEOUT => 5,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_HTTPHEADER => [
					'Host: lk.sut.ru',
					'Accept-Language: ru',
					"Cookie: miden=$cookie",
					'Connection: keep-alive',
					'Accept: text/html, */*; q=0.01',
					'X-Requested-With: XMLHttpRequest',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://lk.sut.ru/cabinet/?login=yes',
					'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
					'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.1 Safari/605.1.15',
				],
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_POSTFIELDS => http_build_query($params),
			]);
			$result = curl_exec($ch);
			curl_close($ch);

			if($error = curl_errno($ch)) {
				if($error == 23) { // Если ты не авторизован, ЛК может прислать 23 код ошибки (curl error)
					return false;
				}
				return $error;
			} elseif($result == "У Вас нет прав доступа. Или необходимо перезагрузить приложение..") {
				return false;
			} elseif($result === false) {
				$result = null;
			}

			return $result;
		}

		#[ArrayShape(['count' => "int", 'messages' => "array", 'sorted_messages' => "array"])]
		public function getMessages(): array|null {
			$response = [];
			$params = ['type' => 'in', 'page' => 1];

			while(true) {
				$messages = $this->request("message", $params);
				if(strpos($messages, "Сообщений не найдено")) {
					if($params['type'] == 'in') {
						$params = [
							'type' => 'out',
							'page' => 1
						];
						continue;
					} else {
						break;
					}
				}

				$doc = new DOMDocument();
				$doc->loadHTML($messages);
				$xpath = new DOMXPath($doc);

				$table = $xpath->query('//*[@id="mytable"]/tbody/tr');
				foreach($table as $tr) {
					$id = str_replace('tr_', '', $tr->getAttribute('id'));
					if(str_starts_with($id, "show_")) continue;

					$tds = $tr->getElementsByTagName('td');
					$files = [];

					foreach($tds[2]->getElementsByTagName('a') as $file) {
						$files[] = $file->getAttribute('href');
					}

					$response[] = [
						'id' => (int)$id,
						'time' => strtotime($tds[0]->textContent),
						'title' => trim(preg_replace('/\s\s+/', '', strip_tags((string)iconv('utf-8', 'iso8859-1', $tds[1]->textContent)))),
						'text' => html_entity_decode(strip_tags(json_decode($this->post("sendto2", ['id' => $id, 'prosmotr' => '']), true)['annotation'])),
						'files' => $files,
						str_replace(['in', 'out'], ['sender', 'receiver'], $params['type']) => str_replace(' (сотрудник/преподаватель)', '', iconv('utf-8', 'iso8859-1', $tds[3]->textContent))
					];
				}

				$params['page'] += 1;
			}

			usort($response, function($msg1, $msg2) {
				return $msg1['time'] < $msg2['time'] ? 1 : -1;
			});

			$sorted_messages = [];
			foreach($response as $message) {
				$key = $message['sender'] ?? $message['receiver'] ?? "Неотсортированные";
				$sorted_messages[$key][] = $message;
			}

			return ['count' => count($response), 'messages' => $response, 'sorted_messages' => $sorted_messages];
		}

		public function getNewMessages(): array|null {
			$messages = $this->request("message");

			$doc = new DOMDocument();
			$doc->loadHTML($messages);
			$xpath = new DOMXPath($doc);

			$table = $xpath->query('//*[@id="mytable"]/tbody/tr');
			$response = [];

			foreach($table as $tr) {
				$id = str_replace('tr_', '', $tr->getAttribute('id'));
				if(str_starts_with($id, "show_")) continue;
				elseif($tr->getAttribute('style') != "font-weight: bold !important;") continue;

				$tds = $tr->getElementsByTagName('td');
				$files = [];

				foreach($tds[2]->getElementsByTagName('a') as $file) {
					$files[] = $file->getAttribute('href');
				}

				$response[] = [
					'id' => (int)$id,
					'time' => strtotime($tds[0]->textContent),
					'title' => trim(preg_replace('/\s\s+/', '', strip_tags((string)iconv('utf-8', 'iso8859-1', $tds[1]->textContent)))),
					'text' => html_entity_decode(strip_tags(json_decode($this->post("sendto2", ['id' => $id, 'prosmotr' => '']), true)['annotation'])),
					'files' => $files,
					'sender' => str_replace(' (сотрудник/преподаватель)', '', iconv('utf-8', 'iso8859-1', $tds[3]->textContent))
				];
			}


			return $response;
		}

		public function getSchedule(string $date = "now 00:00:00"): array|false {
			try {
				if(date('m') < 6) {
					$time_from_september = strtotime("01.09" . (date('Y') - 1));
				} else {
					$time_from_september = strtotime(date('01.09.Y'));
				}
				$need_time = strtotime($date);

				$days = ($need_time - $time_from_september) / (60 * 60 * 24);
				$week = (($days / 7) - floor($days / 7) > 0.65 ? round($days / 7) : floor($days / 7)) + 1;

				$schedule = self::request("raspisanie", ['week' => $week]);
				$doc = new DOMDocument();
				$doc->loadHTML($schedule);
				$xpath = new DOMXPath($doc);

				$table = $xpath->query('//table[@class="simple-little-table"]/tbody/tr');
				$date_is_fined = false;
				$items = [];

				foreach($table as $tr) {
					$is_date = $tr->getAttribute('style') == 'background: #b3b3b3; !important; ';
					if($is_date) {
						if($date_is_fined) break;

						$date = iconv('utf-8', 'iso8859-1', $tr->textContent);
						if(stripos($date, date('d.m.Y', $need_time)) !== false) {
							$date_is_fined = true;
							continue;
						}
					}

					if($date_is_fined) {
						$tds = $tr->getElementsByTagName('td');
						$items[] = [
							'num_with_time' => iconv('utf-8', 'iso8859-1', $tds[0]->textContent),
							'name' => iconv('utf-8', 'iso8859-1', $tds[1]->getElementsByTagName('b')[0]->textContent),
							'type' => iconv('utf-8', 'iso8859-1', $tds[1]->getElementsByTagName('small')[0]->textContent),
							'place' => iconv('utf-8', 'iso8859-1', $tds[2]->textContent),
							'teacher' => iconv('utf-8', 'iso8859-1', $tds[3]->textContent),
						];
					}
				}

				return ['count' => count($items), 'items' => $items];
			} catch(Exception) {
				return false;
			}
		}

	}