<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Help implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();

			$commands = [
				'/расписание, /schedule -- расписание группы',
				'/группа, /group -- привязать группу к чату/себе',
				'/заказать, /order - закать справку об обучении',
				'/привязать, /bind -- привязать ЛК к боту',
				'/отвязать, /unbind -- отвязать ЛК от бота',
				'/отметки, /marking -- отметки на парах',
				'/оценки, /дневник, /marks -- оценки из ЛК',
				'/инфо, /профиль, /info, /profile -- профиль в боте',
				'/пинг, /ping -- пинг бота'
			];

			$vkApi->sendMessage("ℹ️ Доступные команды:\n\n" . implode(";\n", $commands) . "\n\n⚙️ Бот принимает два типа префиксов: / и !", [
				'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/@botbonch-about-me","label":"Подробная инструкция","payload":""}}]],"inline":true}'
			]);
			return true;
		}

	}