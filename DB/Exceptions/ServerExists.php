<?php
	namespace Me\Korolevsky\Api\DB\Exceptions;

	use JetBrains\PhpStorm\Pure;

	class ServerExists extends \Exception {

		#[Pure]
		public function __construct($message = "There is already a server with this key.", $code = 1, \Throwable $previous = null) {
			parent::__construct($message, $code, $previous);
		}

		public function __toString() {
			return __CLASS__ . "Error: ";
		}

	}