<?php
	namespace Me\Korolevsky\BonchBot\Handlers;

	use Me\Korolevsky\BonchBot\Data;
	use Me\Korolevsky\BonchBot\VKApi;
	use Me\Korolevsky\BonchBot\Interfaces\Handler;
	use RedBeanPHP\R;

	class NewPost implements Handler {

		public function __construct(VKApi $vkApi, array $object) {
			if($object['post_type'] != 'post') return false;

			$users = R::getAll('SELECT * FROM `users`');
			$peer_ids = [];

			foreach($users as $user) {
				$settings = json_decode($user['settings'], true);
				if(!$settings['mailing']) continue;

				$peer_ids[] = $user['user_id'];
			}

			$peer_ids_chunks = array_chunk($peer_ids, 99);
			$group_id = Data::GROUP_ID;

			foreach($peer_ids_chunks as $peer_ids) {
				$vkApi->sendMessage(
					"🔔 На стене [club${group_id}|нашего сообщества] вышел новый пост.\n⚙️ Отключить рассылку можно в профиле.",
					[
						'peer_ids' => implode(",", $peer_ids),
						'forward' => [],
						'attachment' => "wall${object['owner_id']}_${object['id']}"
					]
				);
			}

			return true;
		}

	}