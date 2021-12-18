<?php
	namespace Me\Korolevsky\Api;

	class Autoload {

		public function __construct() {
			@include 'vendor/autoload.php';
			self::registerAutoload();
		}

		private function registerAutoload() {
			spl_autoload_register(function(string $classname): void {
				$classname = str_replace('\\', '/', $classname);
				$filepath = explode('Me/Korolevsky/Api/', $classname)[1];

				if(file_exists("$filepath.php")) {
					require "$filepath.php";
				}
			});
		}

	}

	new Autoload();