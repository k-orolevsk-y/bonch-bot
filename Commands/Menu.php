<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Menu implements Command {

		public function __construct(Api $api, array $object) {
			return $api->getVkApi()->sendMessage("ℹ️ Меню бота отправлено.", [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Расписание 📅","payload":"{ \"command\": \"eval\", \"cmd\": \"/schedule\" }"},"color":"primary"}],[{"action":{"type":"text","label":"Заказ справок 📑","payload":"{ \"command\": \"eval\", \"cmd\": \"/order\" }"},"color":"secondary"},{"action":{"type":"text","label":"Профиль 🙇🏻","payload":"{ \"command\": \"eval\", \"cmd\": \"/profile\" }"},"color":"secondary"}],[{"action":{"type":"text","label":"Отметки 🔖","payload":"{ \"command\": \"eval\", \"cmd\": \"/marking\" }"},"color":"negative"},{"action":{"type":"text","label":"Сообщения 📪","payload":"{ \"command\": \"eval\", \"cmd\": \"/messages\" }"},"color":"positive"},{"action":{"type":"text","label":"Оценки 📚","payload":"{ \"command\": \"eval\", \"cmd\": \"/marks\" }"},"color":"negative"}]]}'
			]);
		}

	}