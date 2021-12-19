<?php
	namespace Me\Korolevsky\Api\Utils;

	use Me\Korolevsky\Api\DB\Server;
	use JetBrains\PhpStorm\ArrayShape;

	class Authorization {

		private function __construct() {}

		public static function isAuth(Server $server, string|null $access_token): bool {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return false;
			}

			return true;
		}

		public static function getUserId(Server $server, string $access_token): int {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return 0;
			}

			return $token['user_id'];
		}

		#[ArrayShape(['id' => "int", 'token' => "string"])]
		public static function getAccessToken(Server $server, int $user_id): array {
			$access_token = $server->findOne('access_tokens', 'WHERE `user_id` = ?', [ $user_id ]);
			if(!$access_token->isNull()) {
				return [
					'id' => intval($access_token['id']),
					'token' => $access_token['access_token']
				];
			}

			try {
				$token = bin2hex(random_bytes(24));
			} catch(\Exception) {
				$token = uniqid() . uniqid(). uniqid();
			}

			$access_token = $server->dispense('access_tokens');
			$access_token['user_id'] = $user_id;
			$access_token['access_token'] = $token;
			$access_token['time'] = time();
			$access_token['ip'] = Ip::get();
			$server->store($access_token);

			return [
				'id' => intval($access_token['id']),
				'token' => $token
			];
		}

		public static function checkingLimits(Server $server, string $method, string $access_token): bool {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return false;
			}

			$limits = $server->findOne('limits', 'WHERE `access_token_id` = ? AND `method` = ?', [ $token['id'], $method ]);
			if($limits->isNull()) {
				$limits = $server->dispense('limits');
				$limits['access_token_id'] = $token['id'];
			}
		}

	}