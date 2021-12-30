<?php
	namespace Me\Korolevsky\Api\Utils;

	use Me\Korolevsky\Api\DB\Server;
	use JetBrains\PhpStorm\ArrayShape;
	use Me\Korolevsky\Api\Exceptions\NotSupported;

	class Authorization {

		public static bool $is_auth = false;

		private function __construct() {}

		public static function isAuth(Server $server, string|null $access_token): bool {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return false;
			}

			self::$is_auth = true;
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
			$access_token['without_limits'] = 0;
			$server->store($access_token);

			return [
				'id' => intval($access_token['id']),
				'token' => $token
			];
		}

		public function resetAccessToken(Server $server, string $access_token): ?array {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return null;
			}
			$server->trash($token);

			try {
				$token = bin2hex(random_bytes(24));
			} catch(\Exception) {
				$token = uniqid() . uniqid(). uniqid();
			}

			$access_token = $server->dispense('access_tokens');
			$access_token['user_id'] = $token['user_id'];
			$access_token['access_token'] = $token;
			$access_token['time'] = time();
			$access_token['ip'] = Ip::get();
			$access_token['without_limits'] = 0;
			$server->store($access_token);

			return [
				'id' => intval($access_token['id']),
				'token' => $token
			];
		}

		/**
		 * @throws NotSupported
		 */
		public static function checkingLimits(Server $server, array $limits, string $method, string $access_token): int {
			$token = $server->findOne('access_tokens', "WHERE `access_token` = ?", [ $access_token ]);
			if($token->isNull()) {
				return 0;
			} elseif(@$token['without_limits']) {
				return 1;
			}

			if(!class_exists("Memcached")) {
				throw new NotSupported('PHP not supported need extension: memcached.');
			}

			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			$keys = [
				'method' => "limits_${method}_${access_token}",
				'ip' => "limits_".Ip::get()
			];

			$returned_code = 1;
			foreach($keys as $key) {
				$times = ['second' => 1, 'half_hour' => 1800, 'hour' => 3600];
				foreach($times as $name => $time) {
					$key_for_limits = $name == "second" ? 0 : ($name == "half_hour" ? 1 : 2);
					if($limits[$key_for_limits] < 2) {
						continue;
					}

					$key = "${name}_${key}";
					$limit = $memcached->getByKey($server->getDbName(), $key);
					if(!$limit) {
						$limit = [0, time()+$time];
						$memcached->setByKey($server->getDbName(), $key, $limit, $limit[1]);
					} elseif($limit[1] < time()) {
						$limit = [0, time()+$time];
					}

					$limit[0] += 1;
					$memcached->replaceByKey($server->getDbName(), $key, $limit, $limit[1]);

					if($limit[0] >= $limits[$key_for_limits]) {
						$returned_code = $name == 'second' ? -1 : -2;
					}
				}
			}

			return $returned_code;
		}


		public static function setIsAuth(bool $is_auth): void {
			self::$is_auth = $is_auth;
		}

	}