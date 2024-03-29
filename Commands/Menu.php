<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Menu implements Command {

		public function __construct(Api $api, array $object) {
			if($object['peer_id'] > 2000000000) {
				return $api->getVkApi()->sendMessage("❗️ Данная команда не работает в беседах.");
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($user == null) {
				$bind = R::findOne('chats_bind', 'WHERE `peer_id` = ?', [ $object['peer_id'] ]);
				if($bind != null && $object['peer_id'] <= 2000000000) {
					return $api->getVkApi()->sendMessage("ℹ️ Меню бота отправлено.", [
						'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}]]}'
					]);
				}

				$api->getVkApi()->sendMessage("🚫 У Вас не привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать ЛК","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"positive"}]],"inline":true}',
				]);
				return false;
			}

			return $api->getVkApi()->sendMessage("ℹ️ Меню бота отправлено.", [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}],[{"action":{"type":"text","label":"Заказ справок 📑","payload":"{ \"command\": \"eval\", \"cmd\": \"/order\" }"},"color":"secondary"},{"action":{"type":"text","label":"Профиль 🙇🏻","payload":"{ \"command\": \"eval\", \"cmd\": \"/profile\" }"},"color":"secondary"}],[{"action":{"type":"text","label":"Отметки 🔖","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"},{"action":{"type":"text","label":"Сообщения 📪","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages\" }"},"color":"positive"},{"action":{"type":"text","label":"Оценки 📚","payload":"{ \"command\": \"eval\", \"cmd\": \"/marks\" }"},"color":"negative"}]]}'
			]);
		}

	}