<?php
	namespace Me\Korolevsky\Api\Exceptions;

	use JetBrains\PhpStorm\Pure;

	class NotSupported extends \Exception {

		#[Pure]
		public function __construct($message = "PHP not supported need extension.", $code = 0, \Throwable $previous = null) {
			parent::__construct($message, $code, $previous);
		}

		#[Pure]
		public function __toString(): string {
			return __CLASS__ . 'Error: ' . $this->getMessage();
		}
	}