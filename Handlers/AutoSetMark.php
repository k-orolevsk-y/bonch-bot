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

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::autoload();
			self::getApi();
			self::getSchedule();
			self::start();
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
					$vkApi->sendMessage("📛 Бот не смог авторизоваться в ЛК, для того чтобы установить отметку.\n💡 К сожалению, придется поставить отметку вручную.", [
						'peer_id' => $user['user_id'], 'forward' => []
					]);

					R::trash($item);
					continue;
				} elseif($set_mark == -2) {
					if($item['status'] == 2) {
						$vkApi->sendMessage("⚙️ Отметиться на паре$schedule_name не удалось, будет ещё три попытки отметиться, если не получиться, я пришлю об этом сообщение в диалог.", [
							'peer_id' => $user['user_id'], 'forward' => []
						]);
					}

					$item['status'] += 1;
					if($item['status'] > 5) {
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

				$vkApi->sendMessage("✅ Вы были отмечены на паре$schedule_name.", [
					'peer_id' => $user['user_id'], 'forward' => []
				]);
			}
		}

	}

	new AutoSetMark();