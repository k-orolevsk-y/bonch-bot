<?php
	namespace Me\Korolevsky\BonchBot\Handlers;
	error_reporting(0);

	use Me\Korolevsky\BonchBot\LK;
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
			require '../LK.php';
			require '../Api.php';
			require '../Data.php';
			require '../WebLK.php';
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

				$lk = new LK(intval($item['user_id']));
				if($lk->auth() != 1) {
					$vkApi->sendMessage("📛 Бот не смог авторизоваться в ЛК, для того чтобы установить отметку.\n💡 К сожалению, придётся поставить отметку вручную в ЛК.", [
						'peer_id' => $item['user_id'], 'forward' => []
					]);

					R::trash($item);
					continue;
				}


				$sked = $lk->getSchedule($item['date']);
				$this_lesson = null;

				foreach($sked['items'] as $lesson) {
					if($lesson['num_with_time'] == $item['num_with_time'] && $lesson['teacher'] == $item['teacher']) {
						$this_lesson = $lesson;
						break;
					}
				}

				if($this_lesson == null || $this_lesson['marking']['status'] == -1) {
					$vkApi->sendMessage("️📛 Не удалось отметиться на паре. Бот не смог найти данный предмет в расписании.\n💡 Если занятие всё таки есть, поставьте отметку вручную в ЛК.", [
						'peer_id' => $item['user_id'], 'forward' => []
					]);

					R::trash($item);
					continue;
				}


				$marking = $this_lesson['marking'];
				$schedule_name = "[club".Data::GROUP_ID."|${this_lesson['name']} (${this_lesson['teacher']})]";

				if($marking['status'] == 0) {
					if($item['status'] == 2) {
						$vkApi->sendMessage("⚙️ Отметиться на паре $schedule_name не удалось, будет ещё три попытки отметиться, если не получиться, я пришлю об этом сообщение в диалог.", [
							'peer_id' => $item['user_id'], 'forward' => []
						]);
					}

					$item['status'] += 1;
					if($item['status'] > 5) {
						$vkApi->sendMessage("🚫 Не удалось отметиться на паре $schedule_name, скорее всего преподователь не начал занятие.", [
							'peer_id' => $item['user_id'], 'forward' => []
						]);
						$item['status'] = -1;
					}

					R::store($item);
					continue;
				} elseif($marking['status'] == 2) {
					$vkApi->sendMessage("🤔 Вы уже отметились на паре $schedule_name до бота, какой Вы молодец!", [
						'peer_id' => $item['user_id'], 'forward' => []
					]);

					$item['status'] = 1000;
					R::store($item);
					continue;
				}

				$lk->setMark(intval($marking['id']), intval($sked['week']));
				$item['status'] = 1000;
				R::store($item);

				$vkApi->sendMessage("✅ Вы были отмечены на паре $schedule_name.", [
					'peer_id' => $item['user_id'], 'forward' => []
				]);
			}
		}

	}

	new AutoSetMark();