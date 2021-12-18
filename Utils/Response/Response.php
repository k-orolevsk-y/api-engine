<?php
	namespace Me\Korolevsky\Api\Utils\Response;

	use JetBrains\PhpStorm\NoReturn;

	class Response {

		private int $http_code;
		private OKResponse|ErrorResponse $response;

		#[NoReturn]
		public function __construct(int $http_code = 200, OKResponse|ErrorResponse $response,
		                            bool $need_generation = true) {
			$this->http_code = $http_code;
			$this->response = $response;

			if($need_generation) {
				$this->generateResponse();
			}
		}

		public function getHttpCode(): int {
			return $this->http_code;
		}

		public function __toString(): string {
			return json_encode($this->response->getResponse());
		}

		#[NoReturn]
		public function generateResponse(): void {
			http_response_code($this->http_code);
			header('Access-Control-Allow-Origin: *');
			header('Content-Type: application/json');

			die(strval($this));
		}

	}