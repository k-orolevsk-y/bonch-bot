<?php
	namespace Me\Korolevsky\BonchBot\API;
	require 'Helper.php';

	use RedBeanPHP\R;
	use JetBrains\PhpStorm\NoReturn;

	class SetMark {

		protected array $params;

		#[NoReturn]
		public function __construct() {
			Helper::start();
			$this->params = Helper::checkParams([ 'user_id', 'date', 'num_with_time' ]);
			$this->process();
		}

		#[NoReturn]
		protected function process() {
			return Helper::generateResponse(R::findOne('users', 'WHERE `id` = ?', [ 1 ])->export() ?? []);
		}





	}

	new SetMark();