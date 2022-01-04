<?php
	namespace Me\Korolevsky\Api\Utils\Tasks;

	require 'Autoload.php';
	error_reporting(0);

	use JetBrains\PhpStorm\NoReturn;

	class TaskHandler {

		private array $task;
		private string $task_id;
		private string $server_name;

		/**
		 * TaskHandler constructor.
		 */
		public function __construct() {
			self::initialization();
			self::getData();
			self::start();
		}

		/**
		 * Initializes a new thread (gets task_id, server_name).
		 */
		protected function initialization(): void {
			$task_id = @$_SERVER['argv'][1];
			if(empty($task_id)) {
				die(-1000);
			}

			$server_name = @$_SERVER['argv'][2];
			if(empty($server_name)) {
				die(-2000);
			}

			set_exception_handler(function(\Exception|\Error $exception) {
				$this->shutdown(-1);
			});

			$this->task_id = $task_id;
			$this->server_name = $server_name;
		}

		/**
		 * Get information about a thread from memcached.
		 */
		protected function getData(): void {
			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			$task = $memcached->getByKey($this->server_name, "task_{$this->task_id}");
			if($task === false) {
				$this->shutdown(-3000);
			}

			$this->task = $task;
		}

		/**
		 *  Executes the code and stores the result in memcached.
		 */
		private function start(): void {
			try {
				$func = $this->create_function_php8($this->task['source']['params'], $this->task['source']['code']);
				$result = $func($this->task['servers'], $this->task['params']);

				$memcached = new \Memcached();
				$memcached->addServer('localhost', 11211);

				$this->task['result'] = $result;
				$memcached->setByKey($this->server_name, "task_{$this->task_id}", $this->task, $this->task['time']);
			} catch(\Exception) {
				$this->shutdown(0);
			}

			$this->shutdown(1);
		}

		/**
		 * Terminates the thread.
		 *
		 * @param int $code
		 */
		#[NoReturn]
		private function shutdown(int $code): void {
			if($code != 1 && !empty($this->server_name)) {
				$memcached = new \Memcached();
				$memcached->addServer('localhost', 11211);

				$this->task['result'] = ['error_code' => $code];
				$memcached->setByKey($this->server_name, "task_{$this->task_id}", $this->task, $this->task['time']);
			}

			echo($code);
			die();
		}

		/**
		 * Gets code from string type to PHP code.
		 *
		 * Thanks: https://github.com/lombax85/create_function
		 * @param $args
		 * @param $code
		 * @return \Closure
		 */
		private function create_function_php8($args, $code) {
			$byref = false;
			if(strpos($args, "&") !== false) {
				$byref = true;
			}

			if($byref === true) {
				$func = function(&...$runtimeArgs) use ($args, $code, $byref) {
					return $this->lombax_create_function_closure($args, $code, $runtimeArgs, $byref);
				};
			} else {
				$func = function(...$runtimeArgs) use ($args, $code, $byref) {
					return $this->lombax_create_function_closure($args, $code, $runtimeArgs, $byref);
				};
			}

			return $func;
		}

		/**
		 * For create_function_php8().
		 *
		 * Thanks: https://github.com/lombax85/create_function
		 * @param $args
		 * @param $code
		 * @param $runtimeArgs
		 * @param false $byref
		 * @return mixed
		 * @throws \Exception
		 */
		private function lombax_create_function_closure($args, $code, $runtimeArgs, bool $byref = false): mixed {
			$args = str_replace(" ", "", $args);
			$args = explode(",", $args);

			$i = 0;
			foreach($args as $singleArg) {
				$newArg = $args[$i];

				if(substr($singleArg, 0, 1) == "$") {
					$newArg = str_replace("$", "", $newArg);
					$$newArg = $runtimeArgs[$i];
				} else if(substr($singleArg, 0, 1) == "&") {
					if($byref === true) {
						$newArg = str_replace("&$", "", $newArg);
						$$newArg = &$runtimeArgs[$i];
					} else {
						throw new \Exception("Cannot pass variables by reference, use create_function_php8_byref instead");
					}
				} else {
					throw new \Exception("create_function replacement, not managed case");
				}

				$i++;
			}

			return eval($code);
		}

	}

	new TaskHandler();