<?php
	namespace Me\Korolevsky\BonchBot\API;
	require 'Helper.php';

	use RedBeanPHP\R;
	use JetBrains\PhpStorm\NoReturn;

	class GetUser {

		protected array $params;

		#[NoReturn]
		public function __construct() {
			Helper::start();
			$this->params = Helper::checkParams([ 'vk_id' ]);
			$this->process();
		}

		#[NoReturn]
		protected function process() {
			$user = R::findOne('users', 'WHERE `user_id` = ?', [ $this->params['vk_id'] ]);
			if($user == null) {
				return Helper::generateResponse([ 'error' => true, 'error_description' => 'User not found' ]);
			}

			$user = $user->export();
			return Helper::generateResponse([
				'id' => intval($user['id']),
				'vk_id' => intval($user['user_id']),
				'reg_time' => intval($user['time']),
				'info' => json_decode($user['data'], true),
				'settings' => json_decode($user['settings'], true)
			]);
		}

	}

	new GetUser();