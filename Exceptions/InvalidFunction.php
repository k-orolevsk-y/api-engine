<?php
	namespace Me\Korolevsky\Api\Exceptions;


	use JetBrains\PhpStorm\Pure;

	class InvalidFunction extends \Exception {

		#[Pure]
		public function __construct($message = "Invalid function.", $code = 0, \Throwable $previous = null) {
			parent::__construct($message, $code, $previous);
		}

		public function __toString() {
			return __CLASS__ . "Error: ";
		}

	}