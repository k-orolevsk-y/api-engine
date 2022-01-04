<?php
	namespace Me\Korolevsky\Api\Utils\Tasks;

	use Me\Korolevsky\Api\DB\Server;
	use Me\Korolevsky\Api\DB\Servers;
	use JetBrains\PhpStorm\ArrayShape;
	use Me\Korolevsky\Api\Exceptions\NotSupported;
	use Me\Korolevsky\Api\DB\Exceptions\ServerNotExists;

	class Task {

		private string $task_id;
		protected array $params;
		protected mixed $callable;
		protected int $time_expire;
		protected Servers|Server $servers;

		/**
		 * Task constructor.
		 * WARNING: Parameter types must not be declared in the function. [Because it's not tested]
		 *
		 * @param callable $function
		 * @param Servers|Server $servers
		 * @param array $params
		 * @param int $time_expire
		 */
		public function __construct(callable $function, Servers|Server $servers, array $params, int $time_expire = 300) {
			$this->callable = $function;
			$this->servers = $servers;
			$this->time_expire = $time_expire;
			$this->params = $params;
		}

		/**
		 *  Starts a new thread.
		 *
		 * @throws NotSupported|ServerNotExists
		 */
		public function start(): string|false {
			if(!class_exists("Memcached")) {
				throw new NotSupported('PHP not supported need extension: memcached.');
			} elseif(!(
				function_exists('passthru') &&
				!in_array('passthru', array_map('trim', explode(', ', ini_get('disable_functions')))) &&
				strtolower(ini_get('safe_mode')) != 1)
			) {
				throw new NotSupported("PHP not supported need function: exec.");
			}

			$this->task_id = bin2hex(random_bytes(24));
			if($this->servers instanceof Servers) {
				$server_name = $this->servers->selectServer(0)->getDbName();
			} else {
				$server_name = $this->servers->getDbName();
			}

			try {
				$source_code = $this->getSourceCode($this->callable);
			} catch(\Exception) {
				return false;
			}

			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);
			$memcached->setByKey($server_name, "task_{$this->task_id}", [
				'source' => $source_code,
				'servers' => $this->servers,
				'params' => $this->params,
				'time' => time()+$this->time_expire,
				'result' => 'in_progress'
			], time()+$this->time_expire);

			passthru("php -r 'return;'", $code);
			if($code != 0) {
				throw new NotSupported("Failed to start PHP in external environment. (php -r 'return';)");
			}

			passthru("(php -f Utils/Tasks/TaskHandler.php '{$this->task_id}' '$server_name' & ) >> /dev/null 2>&1");
			return $this->task_id;
		}

		/**
		 * Gets the result of executing another thread.
		 *
		 * @return mixed
		 * @throws ServerNotExists
		 */
		public function getResult(): mixed {
			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			if($this->servers instanceof Servers) {
				$server_name = $this->servers->selectServer(0)->getDbName();
			} else {
				$server_name = $this->servers->getDbName();
			}

			$result = $memcached->getByKey($server_name, "task_{$this->task_id}");
			return $result === false ? false : $result['result'];
		}

		/**
		 * Gets the result of executing another thread. [STATIC]
		 *
		 * @param Servers|Server $servers
		 * @param string $task_id
		 * @return mixed
		 * @throws ServerNotExists
		 */
		public static function getResultS(Servers|Server $servers, string $task_id): mixed {
			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			if($servers instanceof Servers) {
				$server_name = $servers->selectServer(0)->getDbName();
			} else {
				$server_name = $servers->getDbName();
			}

			$result = $memcached->getByKey($server_name, "task_{$task_id}");
			return $result === false ? false : $result['result'];
		}

		/**
		 * Get anon function source code & params.
		 *
		 * Thanks: https://stackoverflow.com/a/7027198
		 * @param callable $function
		 * @return array
		 * @throws \ReflectionException
		 */
		#[ArrayShape(['code' => "string", 'params' => "string"])]
		protected function getSourceCode(callable $function): array {
			$func = new \ReflectionFunction($function);
			$filename = $func->getFileName();
			$start_line = $func->getStartLine();
			$end_line = $func->getEndLine() - 1;
			$length = $end_line - $start_line;

			$source = file($filename);
			$source_code = trim(implode("", array_slice($source, $start_line, $length)));

			$params = [];
			foreach($func->getParameters() as $parameter) {
				$params[] = "$".$parameter->getName();
			}
			$params = implode(', ', $params);

			return [
				'code' => $source_code,
				'params' => $params
			];
		}

	}