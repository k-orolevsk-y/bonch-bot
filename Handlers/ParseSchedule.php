<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;
	use RedBeanPHP\R;

	class ParseSchedule {

		private Api $api;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::autoload();
			self::getApi();
			self::parse();
		}

		#[NoReturn]
		protected function autoload() {
			require '../Api.php';
			require '../Data.php';
			require '../VKApi.php';
			require '../vendor/autoload.php';
		}

		#[NoReturn]
		protected function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
		}

		#[NoReturn]
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
									$data['name'] = trim($info[0]->textContent);
									$data['teacher'] = trim($info[1]->textContent);
									$data['place'] = str_replace('ауд.: ', '', trim($info[2]->textContent));
									$data['type'] = trim($info[3]->textContent);

									$db = R::findOne('schedule_parse', 'WHERE `group_id` = ? AND `date` = ? AND `num_with_time` = ? AND `name` = ?', [ $db_group['id'], $date['text'], $num_with_time, $name ]);
									if($db == null) {
										$db = R::getRedBean()->dispense('schedule_parse');
										$db['group_id'] = $db_group['id'];
										$db['date'] = $date['text'];
										$db['num_with_time'] = $data['num_with_time'];
										$db['name'] = $data['name'];
										$db['teacher'] = $data['teacher'];
										$db['place'] = $data['place'];
										$db['type'] = $data['type'];
									} else {
										foreach($data as $key => $value) {
											if($db[$key] != $value) {
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
	}

	new ParseSchedule();