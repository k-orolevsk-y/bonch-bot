<?php
	namespace Me\Korolevsky\BonchBot\Tasks;

	use Exception;
	use ReflectionException;
	use JetBrains\PhpStorm\ArrayShape;

	class Task {

		private string $task_id;
		protected array $params;
		protected mixed $callable;
		protected int $time_expire;

		/**
		 * Task constructor.
		 * WARNING: Parameter types must not be declared in the function. [Because it's not tested]
		 *
		 * @param callable $function
		 * @param array $params
		 * @param int $time_expire
		 */
		public function __construct(callable $function, array $params, int $time_expire = 300) {
			$this->callable = $function;
			$this->time_expire = $time_expire;
			$this->params = $params;
		}

		/**
		 *  Starts a new thread.
		 *
		 * @throws Exception
		 */
		public function start(): string|false {
			if(!class_exists("Memcached")) {
				throw new Exception('PHP not supported need extension: memcached.');
			} elseif(!(
				function_exists('passthru') &&
				!in_array('passthru', array_map('trim', explode(', ', ini_get('disable_functions')))) &&
				strtolower(ini_get('safe_mode')) != 1)
			) {
				throw new Exception("PHP not supported need function: exec.");
			}

			$this->task_id = bin2hex(random_bytes(24));
			$server_name = __NAMESPACE__;

			try {
				$source_code = $this->getSourceCode($this->callable);
			} catch(Exception) {
				return false;
			}

			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);
			$memcached->setByKey($server_name, "task_{$this->task_id}", [
				'source' => $source_code,
				'params' => $this->params,
				'time' => time() + $this->time_expire,
				'result' => 'in_progress'
			], time() + $this->time_expire);

			passthru("php -r 'return;'", $code);
			if($code != 0) {
				throw new Exception("Failed to start PHP in external environment. (php -r 'return';)");
			}

			passthru("(php -f Tasks/TaskHandler.php '{$this->task_id}' '$server_name' & ) >> /dev/null 2>&1");
			return $this->task_id;
		}

		/**
		 * Gets the result of executing another thread.
		 *
		 * @return mixed
		 */
		public function getResult(): mixed {
			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			$result = $memcached->getByKey(__NAMESPACE__, "task_{$this->task_id}");
			return $result === false ? false : $result['result'];
		}

		/**
		 * Gets the result of executing another thread. [STATIC]
		 *
		 * @param string $task_id
		 * @return mixed
		 */
		public static function getResultS(string $task_id): mixed {
			$memcached = new \Memcached();
			$memcached->addServer('localhost', 11211);

			$result = $memcached->getByKey(__NAMESPACE__, "task_{$task_id}");
			return $result === false ? false : $result['result'];
		}

		/**
		 * Get anon function source code & params.
		 *
		 * Thanks: https://stackoverflow.com/a/7027198
		 * @param callable $function
		 * @return array
		 * @throws ReflectionException
		 */
		#[ArrayShape(['code' => "string", 'params' => "string"])]
		protected function getSourceCode(callable $function): array {
			$func = new \ReflectionFunction($function);
			$filename = $func->getFileName();
			$start_line = $func->getStartLine();
			$end_line = $func->getEndLine() - 1;
			$length = $end_line - $start_line;

			$source = file($filename);
			$source_code = trim(implode("", array_slice($source, $start_line, $length)));

			$params = [];
			foreach($func->getParameters() as $parameter) {
				$params[] = "$" . $parameter->getName();
			}
			$params = implode(', ', $params);

			return [
				'code' => $source_code,
				'params' => $params
			];
		}

	}