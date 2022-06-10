<?php

	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Order implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("⚙️ Информация отправлена Вам в личные сообщения.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);

				$forward = [];
				$object['peer_id'] = $object['from_id'];
			} else {
				$forward = ['is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [$object['from_id']]);
			if($user == null) {
				$vkApi->sendMessage("🚫 У Вас не привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать ЛК","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"positive"}]],"inline":true}',
					'peer_id' => $object['peer_id'],
					'forward' => $forward
				]);
				return false;
			}

			$group_id = Data::GROUP_ID;
			$vkApi->sendMessage(
				"⚠️️ Внимание!\nСтудентам, заказавшим справки и не забравшим их в течение двух недель, в дальнейшем будет отказано в обработке заявок, сделанных через личный кабинет.\nℹ️ Справки изготавливаются в течение [club$group_id|трех рабочих дней] с момента подачи заявления.\n\n📑 Заказ справок.\n❔ Выберите место предоставление справки:",
				[
					'forward' => $forward,
					'peer_id' => $object['peer_id'],
					'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Пенсионный фонд РФ","payload":"{ \"command\": \"order\", \"why\": 0 }"},"color":"primary"},{"action":{"type":"callback","label":"Налоговая инспекция","payload":"{ \"command\": \"order\", \"why\": 1 }"},"color":"primary"}],[{"action":{"type":"callback","label":"Место работы","payload":"{ \"command\": \"order\", \"why\": 2 }"},"color":"secondary"},{"action":{"type":"callback","label":"Место работы родителей","payload":"{ \"command\": \"order\", \"why\": 3 }"},"color":"secondary"}],[{"action":{"type":"callback","label":"СПБ ГКУ «Организатор перевозок»","payload":"{ \"command\": \"order\", \"why\": 4 }"},"color":"primary"},{"action":{"type":"callback","label":"Другое","payload":"{ \"command\": \"order\", \"why\": 5 }"},"color":"primary"}],[{"action":{"type":"callback","label":"Отмена","payload":"{ \"command\": \"cancel\" }"},"color":"negative"}]],"inline":true}',
				]
			);
			return true;
		}

		public static function getWhys(): array {
			return [
				'Территориальный орган Пенсионного фонда РФ',
				'Налоговая инспекция (ФНС, УФНС, ИФНС)',
				'Место работы',
				'Место работы родителей',
				'СПб ГКУ «Организатор перевозок»',
				'Другое'
			];
		}

	}