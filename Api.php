<?php
	namespace Me\Korolevsky\Api;

	use Me\Korolevsky\Api\DB\Server;
	use Me\Korolevsky\Api\DB\Servers;
	use Me\Korolevsky\Api\Utils\Authorization;
	use Me\Korolevsky\Api\Utils\Response\Response;
	use Me\Korolevsky\Api\Exceptions\InvalidFunction;
	use Me\Korolevsky\Api\Utils\Response\ErrorResponse;
	use Me\Korolevsky\Api\Exceptions\MethodAlreadyExists;

	/**
	 * Class Api.
	 * Main class of API.
	 *
	 * @package Me\Korolevsky\Api
	 */
	class Api {

		private array $methods;
		private array $altFunctions; // array of [callable(Server, method_name, ?user_id), need_authorization]
		private mixed $functionNeedAdmin; // PHP 8.0 not supported private class variable of callable
		private array $functionNeedAuthorization;// array of callable, parameter, header/get&post

		/**
		 * API Constructor.
		 *
		 * @param bool $need_custom_exception_handler
		 */
		public function __construct(bool $need_custom_exception_handler = true) {
			if($need_custom_exception_handler) {
				$this->customExceptionHandler();
			}
		}

		/**
		 * Add a method for future processing.
		 *
		 * Example:
		 * <code>
		 *      $api = new Api();
		 *      $api->addMethod("users.get", function(Server $server, array $params): Response {
		 *          $user = $server->findOne("users", "WHERE `user_id` = ?, [ Authorization::getUserId($params['access_token')) ]);
		 *          return new Response(200, new OKResponse([ 'user' => $user ]) ;
		 *      });
		 * </code>
		 *
		 * @param string $method: name of method
		 * @param callable $function: function method
		 * @param array $params: need required params
		 * @param array $limits: limits of [day, hour, 3 seconds]
		 * @param bool $need_authorization: whether it is necessary to check authorization
		 * @param bool $need_admin: whether it is necessary to check administrator rights
		 * @throws MethodAlreadyExists
		 */
		public function addMethod(string $method, callable $function, array $params = [], array $limits = [2, 150, 300],
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
		 *      $api = new Api();
		 *      $api->setNeedAdminFunction(function(Servers|Server $servers, int $user_id): bool {
		 *          $admin = $servers->findOne('admins', 'WHERE `user_id` = ?', [ $user_id ]);
		 *          return !$admin->isNull();
		 *      });
		 * </code>
		 *
		 * @param callable $function
		 * @throws InvalidFunction
		 */
		public function setNeedAdminFunction(callable $function): void {
			try {
				$f = new \ReflectionFunction($function);

				$allowed_types = [ 'Me\Korolevsky\Api\DB\Servers|Me\Korolevsky\Api\DB\Server', 'int' ];
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
		 *      $api = new Api();
		 *      $api->setCustomNeedAuthorizationFunction(function(Servers|Server $servers, string $parameter): bool {
		 *          $admin = $servers->findOne('admins', 'WHERE `x-vk` = ?', [ $parameter ]);
		 *          return !$admin->isNull();
		 *      }, 'x-vk', false);
		 * </code>
		 *
		 * @param callable $function
		 * @param string $parameter: name of parameter
		 * @param bool $type: true - GET/POST parameters, false - Header parameters
		 * @throws InvalidFunction
		 */
		public function setCustomNeedAuthorizationFunction(callable $function, string $parameter, bool $type = true): void {
			try {
				$f = new \ReflectionFunction($function);

				$allowed_types = [ 'Me\Korolevsky\Api\DB\Servers|Me\Korolevsky\Api\DB\Server', 'string' ];
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

		/**
		 * Adds alternative functions for different checks during request processing.
		 *
		 * Example:
		 * <code>
		 *      $api = new Api();
		 *      $api->addAltFunction(function(Servers|Server $servers, array $params): ?Response {
		 *          $random = rand(0,1);
		 *          if($random) {
		 *              return new Response(200, new ErrorResponse(10, "You are won!"));
		 *          }
		 *
		 *          return null;
		 *      });
		 * </code>
		 *
		 * @param callable $function
		 * @throws InvalidFunction
		 */
		public function addAltFunction(callable $function): void {
			try {
				$f = new \ReflectionFunction($function);

				$allowed_types = [ 'Me\Korolevsky\Api\DB\Servers|Me\Korolevsky\Api\DB\Server', 'array' ];
				foreach($f->getParameters() as $reflectionParameter) {
					if(!in_array(strval($reflectionParameter->getType()), $allowed_types)) {
						throw new \Exception();
					}
				}

				$params = $f->getNumberOfRequiredParameters();
				if($params != 2) {
					throw new \Exception();
				}
			} catch(\Exception) {
				throw new InvalidFunction("Alt function is invalid.");
			}

			$this->altFunctions[] = $function;
		}

		/**
		 * Get a merge array of GET and POST parameters.
		 *
		 * @return array
		 */
		public static function getParams(): array {
			$raw_data = json_decode(file_get_contents('php://input'), true);
			if(isset($raw_data)) {
				$_GET = array_merge($_GET, $raw_data);
			}

			return array_change_key_case(array_merge($_GET, $_POST), CASE_LOWER);
		}

		/**
		 * Start the function to process the request.
		 * Notice: the first server will be used to check different data (authorization, limits).
		 * [All Servers/Server are sent to custom handlers]
		 *
		 * @param string $method_name
		 * @param Servers|Server $servers
		 * @return Response
		 * @throws DB\Exceptions\ServerNotExists|Exceptions\NotSupported
		 */
		public function processRequest(string $method_name, Servers|Server $servers): Response {
			if(empty($method_name)) {
				return new Response(404, new ErrorResponse(404, "Error getting method: `method` field can't be empty."));
			} elseif(empty($this->methods[$method_name])) {
				return new Response(404, new ErrorResponse(404, "Unknown method requested."));
			} elseif(!$servers->isConnected()) {
				return new Response(404, new ErrorResponse(500, "Error connecting database, try later.", [ 'db' => [ 'error_message' => $servers->getErrorConnect() ] ]));
			}

			if($servers instanceof Servers) {
				$server = $servers->selectServer(0);
			} else {
				$server = $servers;
			}

			$method = $this->methods[$method_name];
			$params = self::getParams();

			if($method['need_authorization']) {
				if(!empty($this->functionNeedAuthorization)) {
					$func = $this->functionNeedAuthorization;
					if($func[2]) {
						$result = call_user_func($func[0], $servers, strval($params[$func[1]]));

						$param_for_limits = strval($params[$func[1]]);
					} else {
						$params_header = array_change_key_case(getallheaders(), CASE_LOWER);
						$result = call_user_func($func[0], $servers, strval($params_header[$func[1]]));

						$param_for_limits = strval($params_header[$func[1]]);
					}

					if(!$result) {
						return new Response(401, new ErrorResponse(401, "Authorization failed: ${func[1]} was missing or invalid." . (!$func[2] ? " (Header)" : "")));
					} else {
						Authorization::setIsAuth(true);
					}
				} else {
					if(!Authorization::isAuth($server, @$params['access_token'])) {
						return new Response(401, new ErrorResponse(401, "Authorization failed: access_token was missing or invalid."));
					}

					$param_for_limits = @$params['access_token'];
				}

				$limits = Authorization::checkingLimits($server, $method['limits'], $method_name, $param_for_limits);
				if($limits !== 1) {
					return match($limits) {
						0 => new Response(500, new ErrorResponse(500, "Authorization failed: no way to check params.")),
						-1 => new Response(429, new ErrorResponse(429, "Too many requests per second.")),
						-2 => new Response(429, new ErrorResponse(429, "Rate limit reached.")),
						default => new Response(500, new ErrorResponse(500, "Authorization failed: unknown error.")),
					};
				}
			}
			if($method['need_admin']) {
				$user_id = Authorization::getUserId($server, @$params['access_token']);
				if(is_int($user_id)) {
					if(!call_user_func($this->functionNeedAdmin, $servers, $user_id)) {
						return new Response(404, new ErrorResponse(404, "Unknown method requested."));
					}
				}
			}
			if(!empty($this->altFunctions)) {
				foreach($this->altFunctions as $function) {
					call_user_func($function, $servers, self::getParams());
				}
			}
			if(($missed = array_diff($method['params'], array_keys(array_diff($params, [null])))) != null) {
				return new Response(400, new ErrorResponse(400, "Parameters error or invalid: ".array_shift($missed)." a required parameter."));
			}

			$response = call_user_func($method['callable'], $servers, self::getParams());
			if(!$response instanceof Response) {
				return new Response(500, new ErrorResponse(500, "The method didn't return a response."));
			}

			return $response;
		}

		/**
		 * Custom API exceptions handler.
		 */
		private function customExceptionHandler(): void {
			set_exception_handler(function(\Exception|\Error $exception) {
				if(self::getParams()['debug']) {
					return new Response(500, new ErrorResponse(500, "Internal server error.", [ 'server' => [ 'error_message' => $exception->getMessage(), 'error_traceback' => $exception->getTrace() ], ]));
				} else {
					return new Response(500, new ErrorResponse(500, "Internal server error."));
				}
			});
		}
	}