<?php
	namespace Me\Korolevsky\BonchBot;

	class Autoload {

		public function __construct() {
			require __DIR__."/vendor/autoload.php";
			self::registerAutoload();
		}

		private function registerAutoload() {
			spl_autoload_register(function(string $class_name) {
				@$array = explode('\\', $class_name);
				@$class_name = array_pop($array);
				@$namespace = $array[count($array)-1];

				if(file_exists(__DIR__."/${class_name}.php") && $namespace == "BonchBot") { /** Maybe it's just file? */
					require __DIR__."/${class_name}.php";
				} elseif(file_exists(__DIR__."/Commands/${class_name}.php") && $namespace == "Commands") { /** Maybe it's command file? */
					require __DIR__."/Commands/${class_name}.php";
				} elseif(file_exists(__DIR__."/Handlers/${class_name}.php") && $namespace == "Handlers") { /** Maybe it's handler file? */
					require __DIR__."/Handlers/${class_name}.php";
				} elseif(file_exists(__DIR__."/Keyboard/${class_name}.php") && $namespace == "Keyboard") { /** Maybe it's keyboard file? */
					require __DIR__."/Keyboard/${class_name}.php";
				} elseif(file_exists(__DIR__."/Actions/${class_name}.php") && $namespace == "Actions") { /** Maybe it's action file? */
					require __DIR__."/Actions/${class_name}.php";
				} elseif(file_exists(__DIR__."/Interfaces/${class_name}.php") && $namespace == "Interfaces") { /** Maybe it's interface file? */
					require __DIR__."/Interfaces/${class_name}.php";
				}
			});
		}

	}

	new Autoload();