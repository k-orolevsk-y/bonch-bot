<?php
	namespace Me\Korolevsky\BonchBot\Handlers;
	error_reporting(0);

	use Me\Korolevsky\BonchBot\Commands\Marking;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class Notification {

		private Api $api;
		private array $users;

		#[NoReturn]
		public function __construct() {
			if(php_sapi_name() != "cli") die("Hacking attempt!");

			self::autoload();
			self::getApi();
			self::getUsers();
			self::start();
		}

		#[NoReturn]
		private function autoload() {
			require '../Api.php';
			require '../Data.php';
			require '../VKApi.php';
			require '../vendor/autoload.php';
			require '../Interfaces/Command.php';
			require '../Commands/Marking.php';
		}

		#[NoReturn]
		private function getApi() {
			$this->api = new Api(Data::TOKENS['public'], [], false);
		}

		#[NoReturn]
		private function getUsers() {
			$this->users = R::getAll('SELECT * FROM `users`');
		}

		#[NoReturn]
		private function start() {
			if(date('h') > 8) {
				$date = date('d.m.Y', strtotime('+1 day'));
			} else {
				$date = date('d.m.Y');
			}

			$vkApi = $this->api->getVkApi();
			foreach($this->users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['send_notifications']) continue;

				$login = openssl_decrypt(hex2bin($user['login']), 'AES-128-CBC', Data::ENCRYPT_KEY);
				$pass = openssl_decrypt(hex2bin($user['password']), 'AES-128-CBC', Data::ENCRYPT_KEY);

				$schedule = json_decode(exec("python3.9 ../Python/GetSchedule.py $login $pass $date"), true);
				if($schedule == null) continue;
				elseif($schedule['count'] < 1) continue;

				$cache = R::findOne('cache', 'WHERE `user_id` = ? AND `name` = ?', [$user['user_id'], "schedule-$date"]);
				if($cache == null) {
					$cache = R::dispense('cache');
					$cache['user_id'] = $user['user_id'];
					$cache['name'] = "schedule-$date";
					$cache['data'] = json_encode($schedule);
					R::store($cache);
				}

				$type = json_decode($user['settings'], true)['type_marking'] == 0 ? "carousel" : "keyboard";
				if($date == date('d.m.Y')) {
					$vkApi->sendMessage("👋🏻 Доброе утро.\n📝 По расписанию у Вас сегодня " . $this->api->pluralForm($schedule['count'], ['пара', 'пары', 'пар']) . ".\n⚙️ На каких Вас отметить?\n\n🔕 Рассылку о парах можно отключить в профиле.", [
							'peer_id' => $user['user_id'],
							'forward' => []
						] + Marking::getKeyboardOrCarousel($type, $schedule, ['from_id' => $user['user_id']], 0, $date));
				} else {
					$vkApi->sendMessage("👋🏻 Добрый вечер.\n📝 По расписанию у Вас завтра " . $this->api->pluralForm($schedule['count'], ['пара', 'пары', 'пар']) . ".\n⚙️ На каких Вас отметить?\n\n🔕 Рассылку о парах можно отключить в профиле.", [
							'peer_id' => $user['user_id'],
							'forward' => []
						] + Marking::getKeyboardOrCarousel($type, $schedule, ['from_id' => $user['user_id']], 0, $date));
				}
			}
		}

	}

	new Notification();