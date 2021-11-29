<?php
	namespace Me\Korolevsky\BonchBot;

	use Exception;
	use VK\Client\Enums\VKLanguage;
	use VK\Client\VKApiClient;

	class VKApi {

		private VKApiClient $client;

		private array $object;
		private string $access_token;

		public function __construct(string $access_token, array $object, string $version = "5.130") {
			$this->client = new VKApiClient($version, VKLanguage::RUSSIAN);

			$this->access_token = $access_token;
			$this->object = $object;
		}

		public function getUser(?string $template, array $params = []): ?array {
			if($template[0] == '[' && $template[-1] == ']') $user_id = substr(explode('|', $template)[0], 1);
			else {
				$user_id = parse_url($template, PHP_URL_PATH);
				if($user_id == $template) {
					$template_http = 'https://' . $template;
					$user_id = parse_url($template_http, PHP_URL_PATH);
				}
				if($user_id != $template) $user_id = substr($user_id, 1);
				if($user_id == false) $user_id = $template;
			}


			try {
				$user = $this->client->users()->get($this->access_token, [
						'user_ids' => $user_id,
					] + $params)[0];
			} catch(Exception) {
				return null;
			}
			return $user;
		}

		public function getName(int $user_id, string $name_case = "nom"): string {
			$user = self::getUser($user_id, [ 'name_case' => $name_case ]);

			return "[id${user['id']}|${user['first_name']} ${user['last_name']}]";
		}

		public function sendMessage(string $message, array $params = [], ?string $access_token = null): mixed {
			$random_id = $params['random_id'] ?? 0;
			$disable_mentions = $params['disable_mentions'] ?? 1;
			$access_token = $access_token ?? $this->access_token;
			$peer_id = isset($params['peer_ids']) ? null : ($params['peer_id'] ?? $this->object['peer_id']);
			$forward = $params['forward'] ?? ['peer_id' => $peer_id,'conversation_message_ids' => [$this->object['conversation_message_id']],'is_reply' => true];

			if($peer_id == null && $params['peer_ids'] == null) {
				return null;
			}
			if($params['dont_parse_links'] == null) {
				$params['dont_parse_links'] = 1;
			}

			try {
				return $this->client->messages()->send($access_token, [
						'peer_id' => $peer_id,
						'message' => $message,
						'random_id' => $random_id,
						'disable_mentions' => $disable_mentions,
						'forward' => json_encode($forward)
					] + $params);
			} catch(Exception) {
				return null;
			}
		}

		public function editMessage(string $message, int $conversation_message_id, int $peer_id, ?array $params = [], bool $exception_handler_need = true): mixed {
			$access_token = $access_token ?? $this->access_token;
			$params['conversation_message_id'] = $conversation_message_id;
			$params['peer_id'] = $peer_id;
			$params['message'] = $message;
			$params['dont_parse_links'] = 1;
			$params['keep_forward_messages'] = 1;

			try {
				return $this->client->messages()->edit($access_token, $params);
			} catch(Exception) {
				if($exception_handler_need) {
					$params['forward'] = ['is_reply' => true, 'peer_id' => $params['peer_id'], 'conversation_message_ids' => [$params['conversation_message_id']]];
					$message = $params['message'];
					unset($params['conversation_message_id']);
					unset($params['message']);

					return $this->sendMessage($message, $params, $access_token);
				} else {
					return null;
				}
			}
		}

		public function sendSticker(int $sticker_id, array $params = [], ?string $access_token = null): ?int {
			$access_token = $access_token ?? $this->access_token;
			$peer_id = $params['peer_id'] ?? $this->object['peer_id'];
			$disable_mentions = $params['disable_mentions'] ?? 1;

			try {
				return $this->client->messages()->send($access_token, [
						'peer_id' => $peer_id,
						'sticker_id' => $sticker_id,
						'random_id' => 0,
						'disable_mentions' => $disable_mentions
					] + $params);
			} catch(Exception) {
				return null;
			}
		}

		public function useMethod(string $cat, string $method, array $params = [], bool $return_error_code = false): mixed {
			$access_token = $params['access_token'] ?? $this->access_token;

			try {
				return $this->client->$cat()->$method($access_token, $params);
			} catch(Exception $exception) {
				if($return_error_code) {
					return $exception->getCode();
				} else {
					return null;
				}
			}
		}

		public function get(string $method, array $params = []): mixed {
			$params['v'] = "5.130";
			if($params['access_token'] == null) {
				$params['access_token'] = $this->access_token;
			}

			$response = null;
			try {
				$ch = curl_init();
				curl_setopt_array($ch, [
					CURLOPT_URL => "https://api.vk.com/method/$method",
					CURLOPT_POST => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_POSTFIELDS => http_build_query($params)
				]);
				$response = json_decode(curl_exec($ch), true);
				curl_close($ch);
			} catch(Exception) {
				return null;
			}

			return $response;
		}

		public function getClient(): VKApiClient {
			return $this->client;
		}

	}