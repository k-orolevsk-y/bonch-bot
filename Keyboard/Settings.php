<?php
	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use RedBeanPHP\R;

	class Settings implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['user_id'] ]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode([ 'type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных." ])
				]);
				return false;
			}
			$settings = json_decode($user['settings'], true);

			$vkApi->editMessage(
				"⚙️ Настройки:\n\n• Тип ответа сообщений в отметках: " . ($settings['type_marking'] == 0 ? "карусель" : "клавиатура") . "\n• Рассылка о занятиях: " . (!$settings['send_notifications'] ? "отключена" : "включена") . "\n• Рассылка о новых записях: " . (!$settings['mailing'] ? "отключена" : "включена") . "\n• Уведомления о новых сообщениях: " . (!$settings['new_messages'] ? "отключены" : "включены") . "\n• Расписание: " . (!$settings['schedule_from_lk'] ? "с официального сайта" : "из ЛК"),
				$object['conversation_message_id'], $object['peer_id'],
				[
					'keyboard' =>
						'{
							 "buttons": [
							   [
							     {
							       "action": {
							         "type": "callback",
							         "label": "Тип ответа",
							         "payload": "{ \"command\": \"set_settings\", \"key\": \"type_marking\", \"value\": '.intval(!$settings['type_marking']).' }"
							       },
							       "color": "'.(!$settings['type_marking'] ? 'positive' : 'negative').'"
							     },
							     {
							       "action": {
							         "type": "callback",
							         "label": "Рассылка",
							         "payload": "{ \"command\": \"set_settings\", \"key\": \"send_notifications\", \"value\": '.intval(!$settings['send_notifications']).' }"
							       },
							       "color": "'.($settings['send_notifications'] ? 'positive' : 'negative').'"
							     }
							   ],
							   [
							     {
							       "action": {
							         "type": "callback",
							         "label": "Новые записи",
							         "payload": "{ \"command\": \"set_settings\", \"key\": \"mailing\", \"value\": ' . intval(!$settings['mailing']) . ' }"
							       },
							       "color": "' . ($settings['mailing'] ? 'positive' : 'negative') . '"
							     },
							     {
							       "action": {
									"type": "callback",
							         "label": "Новые сообщения",
							         "payload": "{ \"command\": \"set_settings\", \"key\": \"new_messages\", \"value\": ' . intval(!$settings['new_messages']) . ' }"
							       },
							       "color": "' . ($settings['new_messages'] ? 'positive' : 'negative') . '"
							     }
							   ],
							   [
							     {
							       "action": {
							         "type": "callback",
							         "label": "Расписание",
							         "payload": "{ \"command\": \"set_settings\", \"key\": \"schedule_from_lk\", \"value\": ' . intval(!$settings['schedule_from_lk']) . ' }"
							       },
							       "color": "' . ($settings['schedule_from_lk'] ? 'positive' : 'negative') . '"
							     }
							   ],
							   [
							     {
							       "action": {
							         "type": "callback",
							         "label": "Назад",
							         "payload": "{ \"command\": \"eval\", \"cmd\": \"/info\", \"update\": '.$object['conversation_message_id'].' }"
							       },
							       "color": "secondary"
							     }
							   ]
							 ],
							 "inline": true
						}'
				]
			);
			return true;
		}

	}