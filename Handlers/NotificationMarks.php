<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	require '../Autoload.php';
	error_reporting(0);

	use Me\Korolevsky\BonchBot\LK;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class NotificationMarks {

		private Api $api;
		private array $users;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::init();
			self::start();
		}

		private function init() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
			$this->users = R::getAll('SELECT * FROM `users`');
		}


		private function start() {
			$vkApi = $this->api->getVkApi();
			$group_id = Data::GROUP_ID;

			foreach($this->users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['marks_notify']) continue;

				$lk = new LK($user['user_id']);
				if($lk->auth() != 1) continue;

				$marks = $lk->getMarks();
				if($marks == null) {
					continue;
				}

				$marks_db = R::findOne('marks', 'WHERE `user_id` = ?', [ $user['user_id'] ]);
				if($marks_db == null) {
					$marks_db = R::dispense('marks');
					$marks_db['user_id'] = $user['user_id'];
				} else {
					$marks_db_arr = json_decode($marks_db['data'], true);

					foreach($marks as $lesson => $dates) {
						foreach($dates as $date => $mark) {
							if($mark == null) continue;

							$mark_db = $marks_db_arr[$lesson][$date];
							if($mark_db == null) {
								$teacher = $this->getTeacher(intval($user['group_id']), $date, $lesson);
								$vkApi->sendMessage("🔔 Вам выставлена оценка по предмету [club$group_id|$lesson] за [club$group_id|$date]!\n🙇🏻 Преподаватель: [club$group_id|$teacher]\n📝 Оценка: [club$group_id|$mark]", [
									'peer_id' => $user['user_id'],
									'forward' => []
								]);
							} elseif($mark_db != $mark) {
								$teacher = $this->getTeacher(intval($user['group_id']), $date, $lesson);
								$vkApi->sendMessage("🔔 Вам изменена оценка по предмету [club$group_id|$lesson] за [club$group_id|$date]!\n🙇🏻 Преподаватель: [club$group_id|$teacher]\n📝 Оценка: [club$group_id|$mark_db ➡️ $mark]", [
									'peer_id' => $user['user_id'],
									'forward' => []
								]);
							}
						}
					}
				}

				$marks_db['data'] = json_encode($marks);
				R::store($marks_db);
			}
		}

		protected function getTeacher(int $group_id, string $date, string $name): string {
			$schedule = R::getAll('SELECT * FROM `schedule_parse` WHERE `group_id` = ? AND `date` = ? AND `name` LIKE ?', [ $group_id, $date, "%$name%" ]);
			if($schedule == null) {
				return "Неизвестен";
			} else if(count($schedule) < 2) {
				return $schedule[0]['teacher'];
			}

			$teachers = [];
			foreach($schedule as $lesson) {
				if(!in_array($lesson['teacher'], $teachers)) {
					$teachers[] = $lesson['teacher'];
				}
			}

			return implode(' / ', $teachers);
		}

	}

	new NotificationMarks();