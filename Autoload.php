<?php
	namespace Me\Korolevsky\BonchBot;

	class Autoload {

		public function __construct() {
			require "vendor/autoload.php";
			self::registerAutoload();
		}

		private function registerAutoload() {
			spl_autoload_register(function(string $class_name) {
				@$array = explode('\\', $class_name);
				@$class_name = array_pop($array);
				@$namespace = $array[count($array)-1];

				if(file_exists("${class_name}.php") && $namespace == "BonchBot") { /** Maybe it's just file? */
					require "${class_name}.php";
				} elseif(file_exists("Commands/${class_name}.php") && $namespace == "Commands") { /** Maybe it's command file? */
					require "Commands/${class_name}.php";
				} elseif(file_exists("Handlers/${class_name}.php") && $namespace == "Handlers") { /** Maybe it's handler file? */
					require "Handlers/${class_name}.php";
				} elseif(file_exists("Keyboard/${class_name}.php") && $namespace == "Keyboard") { /** Maybe it's keyboard file? */
					require "Keyboard/${class_name}.php";
				} elseif(file_exists("Actions/${class_name}.php") && $namespace == "Actions") { /** Maybe it's action file? */
					require "Actions/${class_name}.php";
				} elseif(file_exists("Interfaces/${class_name}.php") && $namespace == "Interfaces") { /** Maybe it's interface file? */
					require "Interfaces/${class_name}.php";
				}
			});
		}

	}

	new Autoload();