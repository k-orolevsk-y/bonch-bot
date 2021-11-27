<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Handler;
	use Me\Korolevsky\BonchBot\VKApi;

	class InviteChat implements Handler {

		public function __construct(VKApi $vkApi, array $object) {
			$group_id = Data::GROUP_ID;
			if($object['action']->member_id == -$group_id) {
				$vkApi->sendMessage("👋🏻 Привет! Я -- BonchBot, Ваш верный помощник в учебе.\n👉🏻 Я могу показывать расписание на занятия, отмечаться на парах и даже показывать скриншот оценок из ЛК.\n\n🎓 Теперь, вам необходимо привязать группу командой [club$group_id|/группа].",
				[  'forward' => [], 'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/@botbonch-about-me","label":"Подробная инструкция","payload":""}}]],"inline":true}' ]);
			}
			return true;
		}

	}