<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;


	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;

	class Start implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$api->getVkApi()->sendMessage("👋🏻 Привет, я BonchBot!\nХочешь узнать расписание у группы? Или чтобы я отметил тебя на паре? 😉", [
				'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Регистрация в боте","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"positive"}],[{"action":{"type":"open_link","link":"https://vk.com/@botbonch-about-me","label":"Подробная инструкция","payload":""}}]],"inline":true}'
			]);
		}

	}