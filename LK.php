<?php

	namespace Me\Korolevsky\BonchBot;

	use DOMDocument;
	use DOMXPath;
	use RedBeanPHP\R;

	class LK {

		private string $cookie;
		private int $user_id;

		public function __construct(int $user_id) {
			$this->user_id = $user_id;
		}

		public function getCookie(): int {
			$user = R::findOne('users', 'WHERE `user_id` = ?', [$this->user_id]);
			if($user == null) {
				return -1;
			}

			$request = self::request("news", cookie: openssl_decrypt(hex2bin($user['cookie']), 'AES-128-CBC', Data::ENCRYPT_KEY) ?? "");
			if($request === false) {
				$login = openssl_decrypt(hex2bin($user['login']), 'AES-128-CBC', Data::ENCRYPT_KEY);
				$pass = openssl_decrypt(hex2bin($user['password']), 'AES-128-CBC', Data::ENCRYPT_KEY);

				$cookie = exec("python3.9 Python/GetCookie.py '$login' '$pass'");
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
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_HTTPHEADER => [
					'Host: lk.sut.ru',
					'Accept-Language: ru',
					'Connection: keep-alive',
					"Cookie: miden=$cookie;",
					'Accept: text/html, */*; q=0.01',
					'X-Requested-With: XMLHttpRequest',
					'Accept-Encoding: gzip, deflate, br',
					'Referer: https://lk.sut.ru/cabinet/?login=yes',
					'Content-Type: application/x-www-form-urlencoded;',
					'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.1 Safari/605.1.15',
				],
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_RETURNTRANSFER => true,
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
				CURLOPT_POSTFIELDS => http_build_query($params),
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
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

		public function getMessages(): array|null {
			$messages = $this->request("message");

			$doc = new DOMDocument();
			$doc->loadHTML($messages);
			$xpath = new DOMXPath($doc);

			$table = $xpath->query('//*[@id="mytable"]/tbody/tr');
			$response = [];

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
					'text' => json_decode($this->post("sendto2", ['id' => $id, 'prosmotr' => '']), true)['annotation'],
					'files' => $files,
					'sender' => str_replace(' (сотрудник/преподаватель)', '', iconv('utf-8', 'iso8859-1', $tds[3]->textContent))
				];
			}

			return $response;
		}

	}