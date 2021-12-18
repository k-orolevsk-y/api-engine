<?php
	namespace Me\Korolevsky\Api\DB;

	use Me\Korolevsky\Api\DB\Exceptions\ServerExists;
	use Me\Korolevsky\Api\DB\Exceptions\ServerNotExists;

	class Servers {

		/**
		 * @var array
		 */
		private array $servers;

		/**
		 * Servers constructor.
		 */
		public function __construct() {}

		/**
		 * Add server in Servers
		 *
		 * @throws ServerExists
		 */
		public function addServer(Server $server, string|int $key = null): int|string {
			if($key == null) {
				$this->servers[] = $server;
			} else {
				if(!empty($this->servers[$key])) {
					throw new ServerExists();
				}

				$this->servers[$key] = $server;
			}

			return array_search($server, $this->servers);
		}

		/**
		 * Delete server from Servers
		 *
		 * @throws ServerNotExists
		 */
		public function deleteServer(string|int $key): bool {
			if(empty($this->servers[$key])) {
				throw new ServerNotExists();
			}

			unset($this->servers[$key]);
			return true;
		}

		/**
		 * Select server in Servers
		 *
		 * @throws ServerNotExists
		 */
		public function selectServer(string|int $key): Server {
			$server = $this->servers[$key];
			if(empty($server)) {
				throw new ServerNotExists();
			}

			return $server;
		}

		public function isConnected(): bool {
			foreach($this->servers as $server) {
				if(!$server->isConnected()) {
					return false;
				}
			}

			return true;
		}

		public function getErrorConnect(): string {
			foreach($this->servers as $server) {
				if(!$server->isConnected()) {
					return $server->getErrorConnect();
				}
			}

			return "You are successfully connected!";
		}

	}