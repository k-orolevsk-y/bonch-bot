<?php
	namespace Me\Korolevsky\BonchBot\Handlers;
	error_reporting(0);

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class AutoSetMark {

		private Api $api;
		private array $schedule;
		private array $notifications = [];

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::autoload();
			self::getApi();
			self::getSchedule();
			self::start();
			self::sendNotifications();
		}

		#[NoReturn]
		private function autoload() {
			require '../Api.php';
			require '../Data.php';
			require '../VKApi.php';
			require '../vendor/autoload.php';
		}

		#[NoReturn]
		private function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
		}

		#[NoReturn]
		private function getSchedule() {
			$this->schedule = R::getAll("SELECT * FROM `schedule` WHERE `date` = ? AND `status` != ? AND `status` != ?", [ date('d.m.Y'), 1000, -1 ]);
		}

		#[NoReturn]
		private function start() {
			$vkApi = $this->api->getVkApi();
			foreach($this->schedule as $item) {
				$item = R::convertToBean('schedule', $item);

				$exp = explode(' ', $item['num_with_time']);
				if(count($exp) > 1) {
					$time = [
						strtotime(date('d.m.Y '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[0])) - 600,
						strtotime(date('d.m.Y '.explode('-', str_replace(['(', ')', ':'], ['','','.'], $exp[1]))[1]))
					];
				} else {
					$time = [
						strtotime(date('d.m.Y '.explode('-', $item['num_with_time'])[0])) - 600,
						strtotime(date('d.m.Y '.explode('-', $item['num_with_time'])[1]))
					];
				}

				if(!($time[0] < time() && $time[1] > time())) {
					continue;
				}

				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $item['user_id'] ]);
				$login = openssl_decrypt(hex2bin($user['login']),'AES-128-CBC', Data::ENCRYPT_KEY);
				$pass = openssl_decrypt(hex2bin($user['password']),'AES-128-CBC', Data::ENCRYPT_KEY);
				$data = json_decode($user['data'], true);

				$cache = R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [ $item['user_id'], 'schedule-'.date('d.m.Y') ]);
				$schedule_name = "";

				if($cache != null) {
					$sked = json_decode($cache['data'], true)['items'];
					foreach($sked as $s_item) {
						if($s_item['num_with_time'] == $item['num_with_time']) {
							$group_id = Data::GROUP_ID;
							$schedule_name = " [club$group_id|${s_item['name']}]";
						}
					}
				}

				$set_mark = exec("python3.9 ../Python/SetMark.py $login $pass " . str_replace([' ', '(', ')'], '', $item['num_with_time']));
				if($set_mark == 0) {
					$vkApi->sendMessage("📛 Данные от ЛК изменились, бот не смог поставить отметку.", [
						'peer_id' => $user['user_id'], 'forward' => []
					]);

					$schedule = R::getAll('SELECT * FROM `schedule` WHERE `user_id` = ?', [ $user['user_id'] ]);
					R::trashAll(R::convertToBeans('schedule', $schedule));
					R::trash($user);
					continue;
				} elseif($set_mark == -2) {
					if($item['status'] == 2) {
						$vkApi->sendMessage("⚙️ Отметиться на паре$schedule_name не удалось, будет ещё две попытки отметиться, если не получиться, я пришлю об этом сообщение в диалог.", [
							'peer_id' => $user['user_id'], 'forward' => []
						]);
					}

					$item['status'] += 1;
					if($item['status'] > 4) {
						$vkApi->sendMessage("🚫 Не удалось отметиться на паре$schedule_name, скорее всего преподователь не начал занятие.", [
							'peer_id' => $user['user_id'], 'forward' => []
						]);
						$item['status'] = -1;
					}

					R::store($item);
					continue;
				} elseif($set_mark == -3) {
					$vkApi->sendMessage("🤔 Вы уже отметились на паре$schedule_name до бота, какой Вы молодец!", [
						'peer_id' => $user['user_id'], 'forward' => []
					]);

					self::addScheduleStarted($data, [ 'name' => $schedule_name, 'num_with_time' => $item['num_with_time'] ], intval($user['user_id']));
					$item['status'] = 1000;
					R::store($item);
					continue;
				} elseif($set_mark != 1) {
					if($set_mark == null) $set_mark = -100;
					$vkApi->sendMessage("📛 Не удалось отметиться на паре$schedule_name, причина по которой это сделать не получилось неизвестна.\nКод ошибки: $set_mark", [
						'peer_id' => $user['user_id'], 'forward' => []
					]);

					R::trash($item);
					continue;
				}

				$item['status'] = 1000;
				R::store($item);

				self::addScheduleStarted($data, [ 'name' => $schedule_name, 'num_with_time' => $item['num_with_time'] ], intval($user['user_id']));
				$vkApi->sendMessage("✅ Вы были отмечены на паре$schedule_name.", [
					'peer_id' => $user['user_id'], 'forward' => []
				]);
			}
		}

		#[NoReturn]
		private function addScheduleStarted(array $data, array $schedule, int $user) {
			if($this->notifications[$data['group']] == null) {
				$this->notifications[$data['group']] = [
					'num_with_time' => $schedule['num_with_time'],
					'schedule_name' => $schedule['name'],
					'users' =>  array_merge(
						[$user],
						array_column(
							R::getAll(
								'SELECT * FROM `schedule` WHERE `date` = ? AND `num_with_time` = ?',
								[ date('d.m.Y'), $schedule['num_with_time'] ]) ?? [],
							'user_id'
						)
					)
				];
			} else {
				$this->notifications[$data['group']]['users'][] = $user;
			}
		}

		#[NoReturn]
		private function sendNotifications() {
			$users = R::getAll('SELECT * FROM `users`');
			$notifications = $this->notifications;
			$vkApi = $this->api->getVkApi();

			foreach($users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['send_notifications']) continue;

				$data = json_decode($user['data'], true);
				$notification = $notifications[$data['group']];

				if($notification == null) continue;
				elseif(in_array($user['user_id'], $notification['users'])) continue;

//				$vkApi->sendMessage("📝 У Вас началась пара${notification['schedule_name']}!\nℹ️ Необходимо поставить отметку?\n\n🔕 Рассылку о новых парах можно отключить в профиле.", [
//					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Отметить","payload":"{\"command\": \"set_mark\", \"num_with_time\": \"'.$notification['num_with_time'].'\", \"date\": \"'.date('d.m.Y').'\"}"},"color":"positive"}]],"inline":true}',
//					'peer_id' => $user['user_id'],
//					'forward' => []
//				]);
			}
		}

	}

	new AutoSetMark();