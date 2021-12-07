<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;

	class Definition {

		public function __construct(Api $api, array $object) {
			$msg = explode(' ', str_replace("\n", " ", $object['text']));
			$cmd = str_replace('!', '/', mb_strtolower($msg[0]));

			switch($cmd) {
				case "/schedule":
				case "/расписание":
					return new Schedule($api, $object);
				case "/group":
				case "/группа":
					return new Group($api, $object);
				case "/bind":
				case "/привязать":
					return new Bind($api, $object);
				case "/unbind":
				case "/отвязать":
					return new UnBind($api, $object);
				case "/info":
				case "/profile":
				case "/инфо":
				case "/профиль":
					return new Info($api, $object);
				case "/marking":
				case "/отметки":
					return new Marking($api, $object);
				case "/marks":
				case "/оценки":
				case "/дневник":
					return new Marks($api, $object);
				case "/messages":
				case "/сообщения":
					return new Messages($api, $object);
				case "/order":
				case "/заказ":
					return new Order($api, $object);
				case "/help":
				case "/помощь":
				case "/справка":
					return new Help($api, $object);
				case "/ping":
				case "/пинг":
					return new Ping($api, $object);
			}

			return new DefaultCmd($api, $object);
		}

	}