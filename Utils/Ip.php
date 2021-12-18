<?php
	namespace Me\Korolevsky\Api\Utils;

	class Ip {

		public static function get() {
			if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				return $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else if(isset($_SERVER['REMOTE_ADDR'])) {
				return $_SERVER['REMOTE_ADDR'];
			}

			return "unknown";
		}

	}