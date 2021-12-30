<?php
	namespace Me\Korolevsky\Api\DB\Exceptions;

	use JetBrains\PhpStorm\Pure;

	class ServerNotExists extends \Exception {

		#[Pure]
		public function __construct($message = "There is no server with such a key.", $code = 1, \Throwable $previous = null) {
			parent::__construct($message, $code, $previous);
		}

		#[Pure]
		public function __toString(): string {
			return __CLASS__ . 'Error: ' . $this->getMessage();
		}

	}