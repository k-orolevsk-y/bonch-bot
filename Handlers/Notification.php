<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;
	use Me\Korolevsky\BonchBot\Commands\Marking;

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
			require '../LK.php';
			require '../Api.php';
			require '../Data.php';
			require '../VKApi.php';
			require '../WebLK.php';
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
			if(date('H') > 8) {
				$date = date('d.m.Y', strtotime('+1 day'));
			} else {
				$date = date('d.m.Y');
			}

			$vkApi = $this->api->getVkApi();
			foreach($this->users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['send_notifications']) continue;

				$lk = new LK($user['user_id']);
				$auth = $lk->auth();
				if($auth != 1) continue;

				$schedule = $lk->getSchedule($date);
				if($schedule == null) continue;
				elseif($schedule['count'] < 1) continue;

				$type = json_decode($user['settings'], true)['type_marking'] == 0 ? "carousel" : "keyboard";
				if($date == date('d.m.Y')) {
					$marking = R::count('schedule', 'WHERE `user_id` = ? AND `date` = ?', [ $user['user_id'], $date ]);
					if($marking < 1) {
						$last_message_id = $vkApi->useMethod("messages", "search", [ 'q' => '🏻 Добрый вечер. 📝 По расписанию у Вас завтра', 'count' => 1, 'peer_id' => $user['user_id'] ])['items'][0]['conversation_message_id'];
						if(isset($last_message_id)) {
							$vkApi->get("messages.delete", ['peer_id' => $user['user_id'], 'conversation_message_ids' => [$last_message_id], 'delete_for_all' => 1 ]);
						}


						$vkApi->sendMessage("👋🏻 Доброе утро.\n📝 По расписанию у Вас сегодня " . $this->api->pluralForm($schedule['count'], ['пара', 'пары', 'пар']) . ".\n⚙️ На каких Вас отметить?\n\n🔕 Рассылку о парах можно отключить в профиле.", [
								'peer_id' => $user['user_id'],
								'forward' => []
							] + Marking::getKeyboardOrCarousel($type, $schedule, ['from_id' => $user['user_id']], 0, $date));
					}
				} else {
					$last_message_id = $vkApi->useMethod("messages", "search", [ 'q' => '🏻 Доброе утро. 📝 По расписанию у Вас сегодня', 'count' => 1, 'peer_id' => $user['user_id'] ])['items'][0]['conversation_message_id'];
					if(isset($last_message_id)) {
						$vkApi->get("messages.delete", ['peer_id' => $user['user_id'], 'conversation_message_ids' => [$last_message_id], 'delete_for_all' => 1 ]);
					}


					$vkApi->sendMessage("👋🏻 Добрый вечер.\n📝 По расписанию у Вас завтра " . $this->api->pluralForm($schedule['count'], ['пара', 'пары', 'пар']) . ".\n⚙️ На каких Вас отметить?\n\n🔕 Рассылку о парах можно отключить в профиле.", [
							'peer_id' => $user['user_id'],
							'forward' => []
						] + Marking::getKeyboardOrCarousel($type, $schedule, ['from_id' => $user['user_id']], 0, $date));
				}
			}
		}

	}

	new Notification();