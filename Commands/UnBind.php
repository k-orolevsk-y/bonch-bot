<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class UnBind implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$msg = explode(' ', $object['text']);

			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("❗️ Команда не работает в беседах.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);
				return false;
			}

			$db = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($db == null) {
				$vkApi->sendMessage("📛 У Вас не привязаны данные от ЛК.");
				return false;
			}

			if($msg[1] == null) {
				$vkApi->sendMessage("ℹ️ Вы уверены? Данные будут безвозвратно удалены.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Уверен(-а)","payload":"{ \"command\": \"eval\", \"cmd\": \"/unbind 1\" }"},"color":"negative"}]],"inline":true}'
				]);
				return true;
			}

			$schedule = R::getAll('SELECT * FROM `schedule` WHERE `user_id` = ?', [ $object['from_id'] ]);
			$cache = R::getAll('SELECT * FROM `cache` WHERE `user_id` = ?', [ $object['from_id'] ]);
			$marks = R::getAll('SELECT * FROM `marks` WHERE `user_id` = ?', [ $object['from_id'] ]);
			$messages_read = R::getAll('SELECT * FROM `messages_read` WHERE `user_id` = ?', [ $object['from_id'] ]);
			R::trashAll(R::convertToBeans('schedule', $schedule));
			R::trashAll(R::convertToBeans('cache', $cache));
			R::trashAll(R::convertToBeans('marks', $marks));
			R::trashAll(R::convertToBeans('messages_read', $messages_read));
			R::trash($db);

			$vkApi->sendMessage("✅ Данные были безвозвратно удалены.", [
				'keyboard' => '{"buttons":[]}'
			]);
			return true;
		}

	}