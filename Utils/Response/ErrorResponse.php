<?php
	namespace Me\Korolevsky\Api\Utils\Response;

	use Me\Korolevsky\Api\Api;
	use JetBrains\PhpStorm\ArrayShape;
	use Me\Korolevsky\Api\Utils\Authorization;

	class ErrorResponse {

		private int $error_code;
		private string $error_message;
		private array $alt_response;

		public function __construct(int $error_code, string $error_message, array $alt_response = []) {
			$this->error_code = $error_code;
			$this->error_message = $error_message;
			$this->alt_response = $alt_response;
		}

		private function getRequestParams(): array {
			$params = Api::getParams();
			foreach($params as $key => $param) {
				unset($params[$key]);

				if($key == "access_token") {
					$params[] = [
						'key' => 'oauth',
						'value' => (int) Authorization::$is_auth
					];
					continue;
				}

				$params[] = [
					'key' => $key,
					'value' => $param
				];
			}

			return $params;
		}

		#[ArrayShape(['ok' => "false", 'error' => "array", 'request_params' => "array"])]
		public function getResponse(): array {
			return [
				'ok' => false,
				'error' => [
					'error_code' => $this->error_code,
					'error_msg' => $this->error_message,
				] + $this->alt_response,
				'request_params' => $this->getRequestParams()
			];
		}

	}