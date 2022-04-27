<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	require '../Autoload.php';
	error_reporting(0);

	use JetBrains\PhpStorm\ArrayShape;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class ParseSchedule {

		private Api $api;

		private int $start_time;
		private array $logs;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");
			$this->start_time = microtime(true);

			self::getApi();
			self::parse();
		}

		#[NoReturn]
		public function __destruct() {
			$peer_ids = json_decode(R::findOne('settings', 'WHERE `name` = ?', [ 'chats_logs' ])['value'], true);

			$path = '../Files/'.date('d.m.Y-H:i:s').'-bonchbot-ps.log';
			file_put_contents($path, var_export($this->logs, true));

			$doc = $this->api->getVkApi()->uploadFile($path, 171812976);
			if(!$doc) {
				$doc = "https://ssapi.ru/bots/bonch".mb_strcut($path, 2);
			} else {
				unlink($path);
			}

			$this->api->getVkApi()->sendMessage(
				"⚙️ Парсер расписания завершил работу (".round(microtime(true)-$this->start_time, 3)." сек.) и прислал лог-файл, он прикреплён к сообщению.\n\n#parser_schedule",
				[
					'forward' => [],
					'attachment' => $doc,
					'peer_ids' => $peer_ids,
				]
			);
		}

		protected function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
			$this->logs[] = date('[d.m.Y H:i:s]')." Создан экземпляр класса API.";
		}

		protected function parse() {
			$week_get = new \DOMDocument();
			@$week_get->loadHTMLFile("https://www.sut.ru/studentu/raspisanie/raspisanie-zanyatiy-studentov-ochnoy-i-vecherney-form-obucheniya?group=54839");
			$week_get_xpath = new \DOMXPath($week_get);

			preg_match('/([^\)]+)\((.*)\)/', $week_get_xpath->query('//div[@class="vt234"]')->item(0)->textContent, $week);
			$week = intval(array_pop($week))-1;

			$doc = new \DOMDocument();
			@$doc->loadHTMLFile("https://www.sut.ru/studentu/raspisanie/raspisanie-zanyatiy-studentov-ochnoy-i-vecherney-form-obucheniya");
			$xpath = new \DOMXPath($doc);

			$groups = $xpath->query('//a[@class="vt256"]');
			$groups->item(0)->attributes->getNamedItem('data-i');
			foreach($groups as $group) {
				$group = [
					'name' => trim($group->textContent),
					'local_id' => $group->attributes->getNamedItem('data-i')->textContent
				];

				$db_group = R::findOne('groups', 'WHERE `bonch_id` = ?', [ $group['local_id'] ]);
				if($db_group == null) {
					$db_group = R::dispense('groups');
					$db_group['bonch_id'] = $group['local_id'];
					$db_group['name'] = $group['name'];
					R::store($db_group);
				}

				$date = date('Y-m-d', strtotime("-$week week"));
				$this->logs[] = date('[d.m.Y H:i:s]')." Начат парсер расписания для группы ${db_group['name']} (${db_group['bonch_id']}).";

				while(true) {
					$schedule_html = new \DOMDocument();
					@$schedule_html->loadHTMLFile("https://www.sut.ru/studentu/raspisanie/raspisanie-zanyatiy-studentov-ochnoy-i-vecherney-form-obucheniya?group=${group['local_id']}&date=$date");
					$schedule_xpath = new \DOMXPath($schedule_html);

					if($schedule_xpath->query('//div[@class="vt258"]')->length < 1) {
						break;
					}

					$days = $schedule_xpath->query('//*[@class="vt244 vt244a"]')[0]->getElementsByTagName('div');
					$dates = [];

					foreach($days as $day) {
						$day_id = (int) @$day->attributes->getNamedItem('data-i')->textContent;
						if($day_id == 0) continue;

						$dates[] = [
							'id' => $day_id,
							'text' => trim($day->firstChild->textContent) . date('.Y')
						];
					}


					$items = $schedule_xpath->query('//div[@class="vt244"]');
					foreach($items as $item) {
						if($item->C14N() === "") {
							continue;
						}

						$num = $schedule_xpath->query('.//div[@class="vt283"]', $item)[0]->textContent;
						$i_time = explode('<br>', $schedule_xpath->query('.//div[@class="vt239"]', $item)[0]->C14N());
						$time = strip_tags($i_time[1]) . "-" . strip_tags($i_time[2]);

						foreach($dates as $date) {
							$subjects = @$schedule_xpath->query('.//div[@class="vt239 rasp-day rasp-day'.$date['id'].'"]', $item);
							foreach($subjects as $subjects_hour) {
								$subjects = @$schedule_xpath->query('.//div[@class="vt258"]', $subjects_hour);
								foreach($subjects as $subject) {
									$info = $subject->getElementsByTagName('div');

									$data = [];
									if($num == "ФЗ") {
										$data['num_with_time'] = $time;
									} else {
										$data['num_with_time'] = "$num ($time)";
									}
									$data['start'] = $this->parseTime($data['num_with_time'], $date['text'])['start'];
									$data['name'] = trim($info[0]->textContent);
									$data['teacher'] = trim($info[1]->textContent);
									$data['place'] = str_replace('ауд.: ', '', trim($info[2]->textContent));
									$data['type'] = trim($info[3]->textContent);

									$db = R::findOne('schedule_parse', 'WHERE `group_id` = ? AND `date` = ? AND `num_with_time` = ? AND `name` = ?', [ $db_group['id'], $date['text'], $data['num_with_time'], $data['name'] ]);
									if($db == null) {
										$db = R::getRedBean()->dispense('schedule_parse');
										$db['group_id'] = $db_group['id'];
										$db['date'] = $date['text'];
										$db['start'] = $data['start'];
										$db['num_with_time'] = $data['num_with_time'];
										$db['name'] = $data['name'];
										$db['teacher'] = $data['teacher'];
										$db['place'] = $data['place'];
										$db['type'] = $data['type'];

										$this->logs[] = [
											'text' => date('[d.m.Y H:i:s]')." Появилась новая пара в расписании у группы ${db_group['name']} (${db_group['bonch_id']}).",
											'obj' => $data
										];
									} else {
										foreach($data as $key => $value) {
											if($db[$key] != $value) {
												$this->logs[] = [
													'text' => date('[d.m.Y H:i:s]')." У пары ${db['name']} (${db['id']}) изменилось значение $key.",
													'current' => $value,
													'last' => $db[$key]
												];

												$db[$key] = $value;
											}
										}
									}
									R::store($db);
								}
							}
						}
					}

					$date = explode("&date=", $schedule_xpath->query('//a[@class="vt233 vt235"]')[0]->attributes->getNamedItem('href')->textContent)[1];
				}
			}
		}

		#[ArrayShape(['start' => "false|int", 'end' => "false|int"])]
		protected function parseTime(string $num_with_time, string $date): array {
			$exp = explode(' ', $num_with_time);
			if(count($exp) > 1) {
				$time = [
					'start' => strtotime(date("$date ".explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[0])),
					'end' => strtotime(date("$date ".explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]))
				];
			} else {
				$time = [
					'start' => strtotime(date("$date ".explode('-', $num_with_time)[0])),
					'end' => strtotime(date("$date ".explode('-', $num_with_time)[1]))
				];
			}

			return $time;
		}
	}

	new ParseSchedule();