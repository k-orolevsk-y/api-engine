<?php
	namespace Me\Korolevsky\Api;

	use Me\Korolevsky\Api\DB\Server;
	use Me\Korolevsky\Api\DB\Servers;
	use Me\Korolevsky\Api\Exceptions\InvalidFunction;
	use Me\Korolevsky\Api\Utils\Authorization;
	use Me\Korolevsky\Api\Utils\Response\Response;
	use Me\Korolevsky\Api\Utils\Response\ErrorResponse;
	use Me\Korolevsky\Api\Exceptions\MethodAlreadyExists;

	class Api {

		private array $methods;
		private mixed $functionNeedAdmin; // PHP 8.0 not supported private class variable of callable
		private array $functionNeedAuthorization;

		public function __construct(bool $need_custom_exception_handler = true) {
			if($need_custom_exception_handler) {
				$this->customExceptionHandler();
			}
		}

		/**
		 * @throws MethodAlreadyExists
		 */
		public function addMethod(string $method, callable $function, array $params = [], int $limits = 150,
		                          bool $need_authorization = true, bool $need_admin = false): void {
			if(!empty($this->methods[$method])) {
				throw new MethodAlreadyExists();
			}

			$this->methods[$method] = [
				'callable' => $function,
				'params' => $params,
				'limits' => $limits,
				'need_authorization' => $need_authorization,
				'need_admin' => $need_admin
			];
		}

		/**
		 * Installing a function to check administrator rights.
		 * Passed parameters: Server, UserId.
		 *
		 * Example:
		 * <code>
		 *  $api = new Api();
		 *  $api->setNeedAdminFunction(function(Servers|Server $servers, int $user_id): bool {
		 *      $admin = $servers->findOne('admins', 'WHERE `user_id` = ?', [ $user_id ]);
		 *      return !$admin->isNull();
		 *  });
		 * </code>
		 *
		 * @param callable $function
		 * @throws InvalidFunction
		 */
		public function setNeedAdminFunction(callable $function): void {
			try {
				$f = new \ReflectionFunction($function);

				$allowed_types = [ 'Me\Korolevsky\Api\DB\Servers', 'Me\Korolevsky\Api\DB\Server', 'int' ];
				foreach($f->getParameters() as $parameter) {
					if(!in_array(strval($parameter->getType()), $allowed_types)) {
						throw new \Exception();
					}
				}

				$params = $f->getNumberOfRequiredParameters();
				if($params != 2 || strval($f->getReturnType()) !== "bool") {
					throw new \Exception();
				}
			} catch(\Exception) {
				throw new InvalidFunction("setNeedAdmin is invalid.");
			}

			$this->functionNeedAdmin = $function;
		}

		/**
		 * Installing a function to check authorization.
		 * Passed parameters: Server, string for check.
		 *
		 * Example:
		 * <code>
		 *  $api = new Api();
		 *  $api->setCustomNeedAuthorizationFunction(function(Servers|Server $servers, string $parameter): bool {
		 *      $admin = $servers->findOne('admins', 'WHERE `x-vk` = ?', [ $parameter ]);
		 *      return !$admin->isNull();
		 *  }, 'x-vk', false);
		 * </code>
		 *
		 * @param callable $function
		 * @param string $parameter: name of parameter
		 * @param boolean $type: true - GET/POST parameters, false - Header parameters
		 * @throws InvalidFunction
		 */
		public function setCustomNeedAuthorizationFunction(callable $function, string $parameter, bool $type = true): void {
			try {
				$f = new \ReflectionFunction($function);

				$allowed_types = [ 'Me\Korolevsky\Api\DB\Servers', 'Me\Korolevsky\Api\DB\Server', 'string' ];
				foreach($f->getParameters() as $reflectionParameter) {
					if(!in_array(strval($reflectionParameter->getType()), $allowed_types)) {
						throw new \Exception();
					}
				}

				$params = $f->getNumberOfRequiredParameters();
				if($params != 2 || strval($f->getReturnType()) !== "bool") {
					throw new \Exception();
				}
			} catch(\Exception) {
				throw new InvalidFunction("needAuthorization is invalid.");
			}

			$this->functionNeedAuthorization = [$function, $parameter, $type];
		}

		public static function getParams(): array {
			return array_change_key_case(array_merge($_GET, $_POST), CASE_LOWER);
		}

		public function processRequest(string $method, Servers|Server $servers): Response {
			if(empty($this->methods[$method])) {
				return new Response(404, new ErrorResponse(404, "Unknown method requested."));
			} elseif(!$servers->isConnected()) {
				return new Response(404, new ErrorResponse(500, "Error connecting database, try later.", [ 'db' => [ 'error_message' => $servers->getErrorConnect() ] ]));
			}

			if($servers instanceof Servers) {
				$server = $servers->selectServer(0);
			} else {
				$server = $servers;
			}

			$method = $this->methods[$method];
			$params = self::getParams();

			if($method['need_authorization']) {
				if(!empty($this->functionNeedAuthorization)) {
					$func = $this->functionNeedAuthorization;
					if($func[2]) {
						$result = call_user_func($func[0], $servers, strval($params[$func[1]]));
					} else {
						$params_header = array_change_key_case(getallheaders(), CASE_LOWER);
						$result = call_user_func($func[0], $servers, strval($params_header[$func[1]]));
					}

					if(!$result) {
						return new Response(401, new ErrorResponse(401, "Authorization failed: ${func[1]} was missing or invalid." . (!$func[2] ? " (Header)" : "")));
					} else {
						Authorization::setIsAuth(true);
					}
				} else {
					if(!Authorization::isAuth($server, $params['access_token'])) {
						return new Response(401, new ErrorResponse(401, "Authorization failed: access_token was missing or invalid."));
					}
				}
			}
			if($method['need_admin']) {
				$user_id = Authorization::getUserId($server, $params['access_token']);
				if(is_int($user_id)) {
					if(!call_user_func($this->functionNeedAdmin, $servers, $user_id)) {
						return new Response(404, new ErrorResponse(404, "Unknown method requested."));
					}
				}
			}
			if(($missed = array_diff($method['params'], array_keys(array_diff($params, [null])))) != null ) {
				return new Response(400, new ErrorResponse(400, "Parameters error: ".array_shift($missed)." a required parameter."));
			}

			return call_user_func($method['callable'], $servers, self::getParams());
		}

		private function customExceptionHandler(): void {
			set_exception_handler(function(\Exception|\Error $exception) {
				if(self::getParams()['debug']) {
					return new Response(500, new ErrorResponse(500, "Server error.", [ 'server' => [ 'error_message' => $exception->getMessage(), 'error_traceback' => $exception->getTrace() ], ]));
				} else {
					return new Response(500, new ErrorResponse(500, "Server error."));
				}
			});
		}
	}