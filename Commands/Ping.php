<?php
	namespace Me\Korolevsky\BonchBot\Commands;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Api;
	use Me\Korolevsky\BonchBot\Interfaces\Command;

	class Ping implements Command {

		public function __construct(Api $api, array $object) {
			$vkApi = $api->getVkApi("5.155");

			$ping_from_msg = round(microtime(true) - $object['date'], 2);
			if($ping_from_msg < 0) $ping_from_msg = 0;

			$start = microtime(true);
			$id = $vkApi->sendMessage("⚙️ Понг!", [ 'peer_ids' => $object['peer_id'], 'forward' => [ 'is_reply' => true, 'peer_id' => $object['peer_id'], 'conversation_message_ids' => [$object['conversation_message_id']] ] ])[0]['conversation_message_id'];
			$ping = round(microtime(true) - $start, 2);

			$ping_db_start = microtime(true);
			R::getAll('SELECT * FROM `logs`');
			$ping_db = round(microtime(true) - $ping_db_start, 2);

			$ping_processing = round(microtime(true) - $object['date'] - $ping_from_msg, 2);
			if($ping_processing < 0) $ping_processing = 0.01;

			$vkApi->editMessage("⚙️ Анализ завершён.\n\n🧠 На получение сообщения было потрачено: {$ping_from_msg}s.\n📄 На обработку сообщения было потрачено: {$ping_processing}s.\n\n🛠 Запрос к базе данных занимает: {$ping_db}s.\n👉🏻 Пинг до api.vk.com: {$ping}s.", $id, $object['peer_id'], [ 'keep_forward_messages' => 1 ]);
			return true;
		}

	}