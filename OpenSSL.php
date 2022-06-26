<?php
	namespace Me\Korolevsky\BonchBot;

	class OpenSSL {

		public static function encrypt(string $str): string {
			$key = Data::ENCRYPT_KEY;

			$iv = openssl_random_pseudo_bytes(16);
			$encrypt = openssl_encrypt($str, "aes-128-cbc", $key, OPENSSL_RAW_DATA, $iv);

			$hex = bin2hex($iv.$encrypt);
			if(!$hex) {
				return "";
			}

			return $hex;
		}

		public static function decrypt(?string $str): string {
			if($str == null) {
				return "";
			}

			$decode = hex2bin($str);
			$key = Data::ENCRYPT_KEY;

			$data = substr($decode, 16);
			$iv = substr($decode, 0, 16);

			$decrypt = openssl_decrypt($data, 'aes-128-cbc', $key, OPENSSL_RAW_DATA, $iv);
			if(!$decrypt) {
				return "";
			}

			return $decrypt;
		}

	}