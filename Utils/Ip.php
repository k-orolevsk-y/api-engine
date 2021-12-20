<?php
	namespace Me\Korolevsky\Api\Utils;

	class Ip {

		/**
		 * Obtaining an IP address by checking various parameters.
		 *
		 * @return string
		 */
		public static function get(): string {
			if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				return strval($_SERVER['HTTP_X_FORWARDED_FOR']);
			} else if(isset($_SERVER['REMOTE_ADDR'])) {
				return strval($_SERVER['REMOTE_ADDR']);
			}

			return "unknown";
		}

	}