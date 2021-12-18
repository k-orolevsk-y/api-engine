<?php
	namespace Me\Korolevsky\Api\Utils\Response;

	use JetBrains\PhpStorm\ArrayShape;

	class OKResponse {

		private array|string|bool|int $response;

		public function __construct(array|string|bool|int $response) {
			$this->response = $response;
		}

		#[ArrayShape(['ok' => "bool", 'response' => "array"])]
		public function getResponse(): array {
			return [
				'ok' => true,
				'response' => $this->response
			];
		}
	}