<?php
	namespace Me\Korolevsky\Api;

	use Me\Korolevsky\Api\DB\Server;
	use Me\Korolevsky\Api\DB\Servers;
	use Me\Korolevsky\Api\Utils\Authorization;
	use Me\Korolevsky\Api\Utils\Response\Response;
	use Me\Korolevsky\Api\Utils\Response\ErrorResponse;
	use Me\Korolevsky\Api\Exceptions\MethodAlreadyExists;

	class Api {

		private array $methods;

		public function __construct(bool $need_custom_exception_handler = true) {
			if($need_custom_exception_handler) {
				$this->customExceptionHandler();
			}
		}

		/**
		 * @throws MethodAlreadyExists
		 */
		public function addMethod(string $method, callable $function, int $limits = 150,
		                          bool $need_authorization = true, bool $need_admin = false): void {
			if(!empty($this->methods[$method])) {
				throw new MethodAlreadyExists();
			}

			$this->methods[$method] = [
				'callable' => $function,
				'limits' => $limits,
				'need_authorization' => $need_authorization,
				'need_admin' => $need_admin
			];
		}

		public static function getParams(): array {
			return array_merge($_GET, $_POST);
		}

		public function processRequest(string $method, Servers|Server $servers): Response {
			if(empty($this->methods[$method])) {
				return new Response(404, new ErrorResponse(404, "Unknown method requested."));
			} elseif(!$servers->isConnected()) {
				return new Response(404, new ErrorResponse(400, "Error connecting database, try later.", [ 'db' => [ 'error_message' => $servers->getErrorConnect() ] ]));
			}

			if($servers instanceof Servers) {
				$server = $servers->selectServer(0);
			} else {
				$server = $servers;
			}

			$method = $this->methods[$method];
			$params = self::getParams();

			if($method['need_authorization']) {
				if(!Authorization::isAuth($server, $params['access_token'])) {
					return new Response(401, new ErrorResponse(401, "Authorization failed."));
				}
			}

			return call_user_func($method['callable'], $servers, self::getParams());
		}

		private function customExceptionHandler(): void {
			set_exception_handler(function(\Exception|\Error $exception) {
				if(self::getParams()['debug']) {
					return new Response(500, new ErrorResponse(500, "Server error.", [ 'server_error' => $exception->getMessage() ]));
				} else {
					return new Response(500, new ErrorResponse(500, "Server error."));
				}
			});
		}
	}