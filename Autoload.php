<?php
	namespace Me\Korolevsky\Api;

	class Autoload {

		/**
		 * Autoload constructor.
		 */
		public function __construct() {
			@include 'vendor/autoload.php';
			self::registerAutoload();
		}

		/**
		 * Register your own file upload handler by getting the path from the classname.
		 */
		private function registerAutoload() {
			spl_autoload_register(function(string $classname): void {
				$classname = str_replace('\\', '/', $classname);
				$filepath = @explode('Me/Korolevsky/Api/', $classname)[1];

				if(file_exists(__DIR__."/$filepath.php")) {
					require __DIR__."/$filepath.php";
				}
			});
		}

	}

	new Autoload();