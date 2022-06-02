<?php
	namespace Me\Korolevsky\BonchBot;

	use DOMXPath;
	use Exception;
	use DOMDocument;
	use RedBeanPHP\R;
	use Smalot\PdfParser\Parser;
	use JetBrains\PhpStorm\ArrayShape;

	class LK {

		private int $user_id;
		private string $cookie;

		public function __construct(int $user_id) {
			$this->user_id = $user_id;
			define('BONCHBOT_LK_ERROR_TIMEOUT', rand()); // Объявляем глоабльную переменную на ошибку ЛК связанную с падением, чтобы отлавливать её..
		}

		public function auth(): int {
			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $this->user_id ]);
			if($user == null) {
				return -1;
			}

			$request = self::request("profil", cookie: openssl_decrypt(hex2bin($user['cookie']), 'AES-128-CBC', Data::ENCRYPT_KEY) ?? "");
			if($request === BONCHBOT_LK_ERROR_TIMEOUT) {
				return -2; // ЛК в канаве
			} elseif($request === false) {
				if(($cookie = $this->getCookie()) == null) { // Пробуем авторизовать пользователя через запросы, если не получилось - через вебдрайвер
					$webLK = new WebLK($this->user_id);
					if(($cookie = $webLK->getCookie()) == null) { // Если не удалось и через вебдрайвер, то что уж поделать, лк пидорас.
						return 0;
					}
				}

				$user['cookie'] = bin2hex(openssl_encrypt($cookie, 'AES-128-CBC', Data::ENCRYPT_KEY));
				R::store($user);
			}

			$this->cookie = openssl_decrypt(hex2bin($user['cookie']), 'AES-128-CBC', Data::ENCRYPT_KEY);
			return 1;
		}

		public function getCookie(): ?string {
			if(empty($this->user_id)) {
				return null;
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $this->user_id ]);
			$login = openssl_decrypt(hex2bin($user['login']), 'AES-128-CBC', Data::ENCRYPT_KEY);
			$password = openssl_decrypt(hex2bin($user['password']), 'AES-128-CBC', Data::ENCRYPT_KEY);

			$ch = curl_init();
			curl_setopt_array($ch, [ // Получаем от ЛК сформированные куки на главной странице, для создания их авторизованными...
				CURLOPT_URL => 'https://lk.sut.ru/cabinet/',
				CURLOPT_HEADER => true,
				CURLOPT_RETURNTRANSFER => true,
			]);
			$result = curl_exec($ch);
			curl_close($ch);

			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
			$cookies = [];
			foreach($matches[1] as $item) {
				parse_str($item, $cookie);
				$cookies = array_merge($cookies, $cookie);
			}
			$cookie = "miden=${cookies['miden']};uid=${cookies['uid']}"; // Формируем список из miden&uid, отсеивая ненужные нам куки разных защит

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => 'https://lk.sut.ru/cabinet/lib/autentificationok.php',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS => http_build_query([ 'users' => $login, 'parole' => $password ]),
				CURLOPT_HTTPHEADER => [
					"Cookie: $cookie",
				],
			]);
			$result = curl_exec($ch); // Данным запросом мы авторизуем куки на стороне ЛК
			curl_close($ch);

			if(!intval($result)) { // Если 0 - значит ЛК не авторизовался
				return null;
			}

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => 'https://lk.sut.ru/cabinet/?login=yes',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_HTTPHEADER => [
					"Cookie: $cookie",
				],
			]);
			curl_exec($ch); // Данный запрос требуется зачем-то ЛК, без него часть методов будут отдавать ошибку при запросах...
			curl_close($ch);

			return $cookie;
		}

		public function request(string $method, array $params = [], string $cookie = null): string|null|int|false {
			if($cookie === null) {
				$cookie = $this->cookie;
			}

			$ch = curl_init("https://lk.sut.ru/cabinet/project/cabinet/forms/${method}.php?" . http_build_query($params));
			curl_setopt_array($ch, [
				CURLOPT_TIMEOUT_MS => 1500,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_HTTPHEADER => [
					'Host: lk.sut.ru',
					'Accept-Language: ru',
					"Cookie: $cookie",
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
				} elseif($error == 28) {
					return BONCHBOT_LK_ERROR_TIMEOUT;
				}

				return $error;
			} elseif($result == "У Вас нет прав доступа. Или необходимо перезагрузить приложение..") {
				return false;
			} elseif(stripos($result, "index.php?login=no")) {
				return false;
			} elseif($result === false) {
				$result = "";
			}

			return $result;
		}

		public function post(string $method, array $params = [], string $cookie = null): string|null|int|false {
			if($cookie === null) {
				$cookie = $this->cookie;
			}

			$ch = curl_init("https://lk.sut.ru/cabinet/project/cabinet/forms/${method}.php");
			curl_setopt_array($ch, [
				CURLOPT_TIMEOUT_MS => 1500,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_HTTPHEADER => [
					'Host: lk.sut.ru',
					'Accept-Language: ru',
					"Cookie: $cookie",
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
				} elseif($error == 28) {
					return BONCHBOT_LK_ERROR_TIMEOUT;
				}

				return $error;
			} elseif($result == "У Вас нет прав доступа. Или необходимо перезагрузить приложение..") {
				return false;
			} elseif(stripos($result, "index.php?login=no")) {
				return false;
			} elseif($result === false) {
				$result = "";
			}

			return $result;
		}

		public function downloadFile(string $url, string $ext = null): string|false {
			$file = __DIR__.'/Files/'.uniqid();
			if($ext == null) {
				$file .= '.' . pathinfo(explode('?', $url)[0], PATHINFO_EXTENSION);
			} else {
				$file .= ".$ext";
			}
			$fp = fopen($file, 'w+');

			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_URL => $url,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT_MS => 1500,
				CURLOPT_HTTPHEADER => [
					"Cookie: $this->cookie",
				],
				CURLOPT_CUSTOMREQUEST => 'GET',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			]);
			$result = curl_exec($ch);
			curl_close($ch);

			fputs($fp, $result);
			fclose($fp);

			return $result ? $file : false;
		}

		#[ArrayShape(['count' => "int", 'messages' => "array", 'sorted_messages' => "array"])]
		public function getMessages(): array|null {
			$response = [];
			$params = ['type' => 'in', 'page' => 1];

			while(true) {
				$messages = $this->request("message", $params);
				if($messages == null) {
					return null;
				}

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
						'id' => (int) $id,
						'time' => strtotime($tds[0]->textContent),
						'title' => trim(preg_replace('/\s\s+/', '', strip_tags((string)iconv('utf-8', 'iso8859-1', $tds[1]->textContent)))),
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


		public function getMessageText(int $id): string {
			return html_entity_decode(strip_tags(json_decode($this->post("sendto2", ['id' => $id, 'prosmotr' => '']), true)['annotation'])) ?? "";
		}

		public function getNewMessages(): array|null {
			$messages = $this->request("message");
			if($messages == null) return null;

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
					'id' => (int) $id,
					'time' => strtotime($tds[0]->textContent),
					'title' => trim(preg_replace('/\s\s+/', '', strip_tags((string)iconv('utf-8', 'iso8859-1', $tds[1]->textContent)))),
					'text' => html_entity_decode(strip_tags(json_decode($this->post("sendto2", ['id' => $id, 'prosmotr' => '']), true)['annotation'])),
					'files' => $files,
					'sender' => str_replace(' (сотрудник/преподаватель)', '', iconv('utf-8', 'iso8859-1', $tds[3]->textContent)) ?? "Система"
				];
			}

			return $response;
		}

		public function getFilesGroup(): array|null {
			// При большом количестве файлов группы происходит слишком долгая загрузка, чтобы этого не было кешируем файлы группы...
			$cache = R::findOne('cache', 'WHERE `name` = ? AND `user_id` = ?', [ 'files_group', $this->user_id ]);
			if($cache != null) {
				if($cache['time'] < (time()-90)) {
					R::trash($cache);
				} else {
					return json_decode($cache['data'], true);
				}
			}

			$response = [];
			$page = 1;


			while(true) {
				$files = $this->request("files_group_pr", [ 'page' => $page ]);
				if(str_contains($files, "Файлов пока нет.")) break;

				$doc = new DOMDocument();
				$doc->loadHTML($files);
				$xpath = new DOMXPath($doc);

				$table = $xpath->query('//table[@id="mytable"]/tbody/tr');
				foreach($table as $tr) {
					$id = str_replace('tr_', '', $tr->getAttribute('id'));
					if(str_starts_with($id, "show")) continue;

					$tds = $tr->getElementsByTagName('td');
					$files = [];

					foreach($tds[5]->getElementsByTagName('a') as $file) {
						$files[] = $file->getAttribute('href');
					}

					$response[] = [
						'id' => intval($id),
						'sender' => iconv('utf-8', 'iso8859-1', $tds[1]->textContent),
						'time' => strtotime($tds[2]->textContent),
						'title' => iconv('utf-8', 'iso8859-1', $tds[3]->textContent),
						'text' => iconv('utf-8', 'iso8859-1', $tds[4]->textContent),
						'files' => $files
					];
				}

				$page += 1;
			}

			if($response != null) {
				$cache = R::dispense('cache');
				$cache['name'] = 'files_group';
				$cache['user_id'] = $this->user_id;
				$cache['time'] = time();
				$cache['data'] = json_encode($response);
				R::store($cache);
			}

			return $response;
		}

		public function getNewFilesGroup(): array {
			$read = R::findOne('messages_read', 'WHERE `user_id` = ?', [ $this->user_id ]);
			if($read == null) {
				$read = R::getRedBean()->dispense('messages_read');
				$read['user_id'] = $this->user_id;
				$read['data'] = json_encode([]);
			}
			$read_arr = json_decode($read['data'], true);

			$files = $this->request("files_group_pr", [ 'page' => 1 ]);
			if(str_contains($files, "Файлов пока нет.")) {
				return [];
			}

			$doc = new DOMDocument();
			$doc->loadHTML($files);
			$xpath = new DOMXPath($doc);

			$table = $xpath->query('//table[@id="mytable"]/tbody/tr');
			$files_group = [];

			foreach($table as $tr) {
				$id = str_replace('tr_', '', $tr->getAttribute('id'));
				if(str_starts_with($id, "show")) continue;

				$tds = $tr->getElementsByTagName('td');
				$files = [];

				foreach($tds[5]->getElementsByTagName('a') as $file) {
					$files[] = $file->getAttribute('href');
				}

				$files_group[] = [
					'id' => intval($id),
					'sender' => iconv('utf-8', 'iso8859-1', $tds[1]->textContent),
					'time' => strtotime($tds[2]->textContent),
					'title' => iconv('utf-8', 'iso8859-1', $tds[3]->textContent),
					'text' => iconv('utf-8', 'iso8859-1', $tds[4]->textContent),
					'files' => $files
				];
			}

			foreach($files_group as $key => $message) {
				if(in_array($message['id'], $read_arr)) {
					unset($files_group[$key]);
				} else {
					$read_arr[] = $message['id'];
				}
			}

			$read['data'] = json_encode($read_arr);
			R::store($read);

			return array_values($files_group);
		}

		public function setMark(int $id, int $week): string|int|bool|null {
			return $this->post('raspisanie', [
				'open' => 1,
				'rasp' => $id,
				'week' => $week
			]);
		}

		public function getSchedule(string $date = "now 00:00:00"): array|false {
			try {
				if(date('m') < 9) {
					$time_from = strtotime('01.09.'.(date('Y')-1));
				} else {
					$time_from = strtotime(date('01.09.Y'));
				}
				$need_time = strtotime($date);

				$days = ($need_time - $time_from) / (60 * 60 * 24);
				$week = (($days / 7) - floor($days / 7) > 0.65 ? round($days / 7) : floor($days / 7)) + 1;

				$schedule = $this->request("raspisanie", ['week' => $week]);
				if($schedule == null) {
					return false;
				}

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

						$btn = $tds[4]->getElementsByTagName('a');
						if($btn[0] != null) {
							if($btn[1] != null) {
								$attr = $btn[1]->getAttribute('onclick');
								preg_match('/([^\)]+)\((.*)\)/', $attr, $matches); // Получаем данные из скобок кнопки

								$marking = [
									'id' => intval(explode(',', $matches[2])[0]),
									'status' => stripos($attr, 'update_zan') !== false ? 0 : (stripos($attr, 'open_zan') !== false ? 1 : -1),
									'remote' => $btn[0]->getAttribute('href')
								];
							} else {
								$attr = $btn[0]->getAttribute('onclick');
								preg_match('/([^\)]+)\((.*)\)/', $attr, $matches); // Получаем данные из скобок кнопки

								$marking = [
									'id' => intval(explode(',', $matches[2])[0]),
									'status' => stripos($attr, 'update_zan') !== false ? 0 : (stripos($attr, 'open_zan') !== false ? 1 : -1),
									'remote' => null,
								];
							}
						} else {
							$marking = [
								'id' => 0,
								'status' => $tds[4]->textContent == null ? -1 : 2
							];
						}

						$items[] = [
							'num_with_time' => iconv('utf-8', 'iso8859-1', $tds[0]->textContent),
							'name' => iconv('utf-8', 'iso8859-1', $tds[1]->getElementsByTagName('b')[0]->textContent),
							'type' => explode('  занятие началось', iconv('utf-8', 'iso8859-1', $tds[1]->getElementsByTagName('small')[0]->textContent))[0],
							'place' => iconv('utf-8', 'iso8859-1', $tds[2]->textContent),
							'teacher' => iconv('utf-8', 'iso8859-1', $tds[3]->textContent),
							'marking' => $marking
						];
					}
				}

				return ['count' => count(array_unique(array_column($items, 'num_with_time'))), 'week' => $week,  'items' => $items];
			} catch(Exception) {
				return false;
			}
		}

		public function getMarks(): ?array {
			try {
				$marks = $this->request("jurnal_dnevnik", [ 'key' => '6119' ]); // ЛК запрашивает какой-то key, отправляю туда рандомное значение из исходного кода ЛК
				if($marks == null) {
					return null;
				}

				$doc = new DOMDocument();
				$doc->loadHTML($marks);
				$xpath = new DOMXPath($doc);

				$items = [];

				$thead = $xpath->query('//table[@class="smalltab simple-little-table"]/thead/tr/th');
				foreach($thead as $key => $th) {
					if(in_array($key, [0,1]) || empty($th->textContent)) continue; // Скипаем номер недели и дату (или если предмет пустой)...

					$title = iconv('utf-8', 'iso8859-1', $th->textContent);
					$items[$title] = [];
				}

				$keys = array_keys($items);
				$tbody = $xpath->query('//table[@class="smalltab simple-little-table"]/tbody/tr');

				foreach($tbody as $tr) {
					$date = $tr->getAttribute('date1');
					if($date == null) {
						continue;
					}
					$date = date('d.m.Y', strtotime($date));


					$tds = $tr->getElementsByTagName('td');
					foreach($tds as $key => $td) {
						if(in_array($key, [0,1]) || empty($td->textContent)) {
							continue;
						}

						$style = $td->getAttribute('style');
						if($style != null) { // Проверка на пустоту в ячейке
							continue;
						}

						// хз че это делает, но оно убирает тупые пробелы от ЛК
						$string = htmlentities(iconv('utf-8', 'iso8859-1', $td->textContent), null, 'utf-8');
						$content = str_replace(" ", "", $string);
						$mark = html_entity_decode($content);

						if($mark == null) {
							$items[$keys[$key-2]][$date] = null;
						} else {
							$items[$keys[$key-2]][$date] = $mark;
						}
					}
				}
			} catch(Exception) {
				return null;
			}

			return $items;
		}

		public function getOnlyMarks(): ?array {
			$marks = $this->getMarks();
			if($marks == null) {
				return null;
			}

			$result = [];
			foreach($marks as $array_marks) {
				foreach($array_marks as $mark) {
					$result['pass'] += preg_match_all('%Н%', $mark);
					$result['bad'] += preg_match_all('%2%', $mark);
					$result['not_bad'] += preg_match_all('%3%', $mark);
					$result['good'] += preg_match_all('%4%', $mark);
					$result['well'] += preg_match_all('%5%', $mark);
				}
			}

			return $result;
		}

		#[ArrayShape(['title' => "string", 'members' => "array"])]
		public function getGroupMembers(): array {
			$schedule = $this->request("raspisanie");

			$doc = new DOMDocument();
			$doc->loadHTML($schedule);
			$xpath = new DOMXPath($doc);

			$link = $xpath->query('//a[@class="style_gr"]')->item(0)->attributes->getNamedItem('href')->textContent;
			$file = $this->downloadFile($link, 'pdf');

			$parser = new Parser();
			$pdf = $parser->parseFile($file);
			$text = explode("\n", $pdf->getText());

			$result = [
				'title' => $text[0],
				'members' => []
			];
			foreach($text as $str) {
				$arr = explode(' ', $str);
				if(!is_numeric($arr[0])) continue;

				$result['members'][] = implode(' ', array_slice($arr, 1));
			}
			unlink($file);

			return $result;
		}

	}