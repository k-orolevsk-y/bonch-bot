<?php

	namespace Me\Korolevsky\BonchBot\Keyboard;

	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\OpenSSL;
	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Keyboard;
	use Me\Korolevsky\BonchBot\Commands\Order as OrderCmd;

	class OrderConfirm implements Keyboard {

		public function __construct(Api $api, array $object, array $payload) {
			$vkApi = $api->getVkApi();

			if(!in_array($payload['why'], [0, 1, 2, 3, 4, 5]) || iconv_strlen($payload['goal']) > 150) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Данные повреждены."])
				]);
				return false;
			}

			$user = R::findOne('users', 'WHERE `user_id` = ?', [$object['user_id']]);
			if($user == null) {
				$vkApi->get("messages.sendMessageEventAnswer", [
					'peer_id' => $object['peer_id'],
					'user_id' => $object['user_id'],
					'event_id' => $object['event_id'],
					'event_data' => json_encode(['type' => 'show_snackbar', 'text' => "📛 Ваш профиль не найден в базе данных."])
				]);
				return false;
			}

			$types = OrderCmd::getWhys();
			$date_of_manufacture = $this->getFutureBusinessDay(3);
			$login = OpenSSL::decrypt($user['login']);
			$pass = OpenSSL::decrypt($user['password']);

			$vkApi->editMessage("📑 Заказываем справку...", $object['conversation_message_id'], $object['peer_id']);
			

			$params = json_encode([
				'login' => $login,
				'pass' => $pass,
				'why' => $types[$payload['why']],
				'goal' => $payload['goal']
			]);
			$order = exec("python3.9 Python/OrderCertificate.py '$params'");
			if($order != 1) {
				$vkApi->editMessage("❗️ Заказать справку не получилось.", $object['conversation_message_id'], $object['peer_id']);
				return false;
			}

			$vkApi->editMessage("📑 Справка была успешно заказана.\n📅 Примерная дата изготовления справки: $date_of_manufacture\n\n❔ Место предоставления: ${types[$payload['why']]}\n✏️ Цель получения: ${payload['goal']}", $object['conversation_message_id'], $object['peer_id']);
			return true;
		}

		private function getFutureBusinessDay(int $offset): string {
			$offset_added = 0;
			while($offset_added <= $offset) {
				$w = date('w', strtotime('+' . ($offset_added + 1) . ' day'));
				if(in_array($w, [0, 6])) {
					$offset += 1;
				}

				$offset_added += 1;
			}

			return date('d.m.Y', strtotime("+${offset} day"));
		}

	}