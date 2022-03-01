<?php
	namespace Me\Korolevsky\BonchBot\API;

	use RedBeanPHP\R;
	use Me\Korolevsky\BonchBot\Data;
	use JetBrains\PhpStorm\NoReturn;

	class Helper {

		public static function start() {
			require '../Data.php';
			require '../vendor/autoload.php';

			$db = Data::DB_INFO;
			R::setup("mysql:host=${db['host']};dbname=${db['dbname']}", $db['user'], $db['pass']);

			if(!R::testConnection()) {
				Helper::generateResponse([ 'error' => true, 'error_description' => 'DB not connected' ]);
			}
		}

		public static function checkParams(array $need_params) {
			$params = array_merge($_GET, $_POST);
			if(($missed = array_diff($need_params, array_keys(array_diff($params, [null])))) != null) {
				return Helper::generateResponse([ 'error' => true, 'error_description' => "Params missing (".array_shift($missed).")" ]);
			}

			return $params;
		}

		#[NoReturn]
		public static function generateResponse(array $params) {
			header('Access-Control-Allow-Origin: *');
			header('Content-Type: application/json');

			die(json_encode($params));
		}

	}