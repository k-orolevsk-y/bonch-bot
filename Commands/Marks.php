<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\LK;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Marks implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi();
			$payload = (array) $object['payload'];

			if($object['from_id'] == null) {
				$object['from_id'] = $object['user_id'];
			}

			if($object['peer_id'] > 2000000000) {
				$vkApi->sendMessage("⚙️ Информация отправлена Вам в личные сообщения.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"open_link","link":"https://vk.com/im?sel=-207206992","label":"Перейти в ЛС Бота","payload":""}}]],"inline":true}'
				]);

				$forward = [];
				$object['peer_id'] = $object['from_id'];
			} else {
				$forward = [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']]];
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $object['from_id'] ]);
			if($user == null) {
				$vkApi->sendMessage("📛 У Вас не привязаны данные от ЛК.", [
					'keyboard' => '{"buttons":[[{"action":{"type":"text","label":"Привязать","payload":"{ \"command\": \"eval\", \"cmd\": \"/bind\" }"},"color":"secondary"}]],"inline":true}',
					'peer_id' => $object['peer_id'],
					'forward' => $forward
				]);
				return false;
			}

			if($payload['update'] == null) {
				$conversation_message_id = $vkApi->sendMessage("📘 Получаю данные из ЛК...", [
						'peer_ids' => $object['peer_id'],
						'forward' => $forward
					]
				)[0]['conversation_message_id'];
			} else {
				$conversation_message_id = $payload['update'];
				$vkApi->editMessage("📘 Получаю данные из ЛК...", $conversation_message_id, $object['peer_id']);
			}

			$lk = new LK($object['from_id']);
			if($lk->auth() != 1) {
				$vkApi->editMessage("", $conversation_message_id, $object['peer_id']);
				return true;
			}

			$marksLK = $lk->getOnlyMarks();
			if(($marksLK['well']+$marksLK['good']) < 1 || ($marksLK['not_bad']+$marksLK['bad']) < 1) { // Если одна из сумм чисел 0, то в процентном считать не будем
				$text = "🎓 Информация по оценкам на данный семестр:\n\n🚔 Количество пропусков: ${marksLK['pass']}\n☀️ Количество оценок (5/4/3/2): ${marksLK['well']}/${marksLK['good']}/${marksLK['not_bad']}/${marksLK['bad']}.";
			} else {
				$percent = [
					round(($marksLK['well']+$marksLK['good'])/($marksLK['well']+$marksLK['good']+$marksLK['not_bad']+$marksLK['bad'])*100, 1),
					round(($marksLK['not_bad']+$marksLK['bad'])/($marksLK['well']+$marksLK['good']+$marksLK['not_bad']+$marksLK['bad'])*100, 1)
				];

				$text = "🎓 Информация по оценкам на данный семестр:\n\n🚔 Количество пропусков: ${marksLK['pass']}\n☀️ Количество оценок (5/4/3/2): ${marksLK['well']}/${marksLK['good']}/${marksLK['not_bad']}/${marksLK['bad']}\n🧠 Процентное соотношение хороших и плохих оценок: ${percent[0]}% на ${percent[1]}%.";
			}

			$vkApi->editMessage($text, $conversation_message_id, $object['peer_id'], [ 'keyboard' => '{"buttons":[[{"action":{"type":"callback","label":"Получить скриншот оценок","payload":"{ \"command\": \"screen_marks\", \"for\": '.$object['from_id'].', \"update\": '.$conversation_message_id.' }"},"color":"positive"}]],"inline":true}' ]);
			return true;
		}

	}