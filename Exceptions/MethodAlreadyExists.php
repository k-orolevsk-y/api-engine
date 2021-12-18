<?php
	namespace Me\Korolevsky\Api\Exceptions;

	use Exception;
	use Throwable;
	use JetBrains\PhpStorm\Pure;

	class MethodAlreadyExists extends Exception {

		#[Pure]
		public function __construct($message = "Method already exists.", $code = 1, Throwable $previous = null) {
			parent::__construct($message, $code, $previous);
		}

		public function __toString() {
			return __CLASS__ . "Error: ";
		}
	}