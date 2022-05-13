<?php
	namespace Me\Korolevsky\BonchBot;

	use Exception;
	use RedBeanPHP\R;
	use Facebook\WebDriver\WebDriverBy;
	use Facebook\WebDriver\Chrome\ChromeDriver;
	use Facebook\WebDriver\Chrome\ChromeOptions;
	use Facebook\WebDriver\Remote\DesiredCapabilities;
	use Facebook\WebDriver\Remote\WebDriverCapabilityType;

	class WebLK {

		protected string $user_login;
		protected string $user_pass;
		protected int $user_id;
		protected ChromeDriver $driver;

		public function __construct(?int $user_id, string $login = null, string $password = null) {
			if(isset($login) && isset($password)) {
				$this->user_login = $login;
				$this->user_pass = $password;
			} elseif(isset($user_id)) {
				$user = R::findOne('users', 'WHERE `user_id` = ?', [ $user_id ]);
				$this->user_id = $user_id;

				$this->user_login = openssl_decrypt(hex2bin($user['login']), 'AES-128-CBC', Data::ENCRYPT_KEY);
				$this->user_pass = openssl_decrypt(hex2bin($user['password']), 'AES-128-CBC', Data::ENCRYPT_KEY);
			}

			$this->createChromeDriver();
		}

		public function __destruct() {
			if(isset($this->driver)) {
				try { // Пробуем завершить вебдрайвер, чтобы он не оставался в фоне и не грузил оперативку на сервере
					$this->driver->quit();
				} catch(Exception) {}
			}
		}

		protected function createChromeDriver() {
			$options = new ChromeOptions();
			$options->addArguments(array('--no-sandbox', '--headless', '--incognito', 'window-size=1720,2880', '--start-maximized'));

			$capabilities = new DesiredCapabilities([
				WebDriverCapabilityType::BROWSER_NAME => 'chrome',
			]);
			$capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

			try { // Обертка в try {} catch {} защищает в случае неудачного старта вебдрайвера
				putenv('WEBDRIVER_CHROME_DRIVER=/usr/lib/chromium-browser/chromedriver');
				$this->driver = ChromeDriver::start($capabilities);
				$this->driver->manage()->timeouts()->pageLoadTimeout(2); // В случае падений ЛК таймаут поможет от "вечной" работы скрипта
			} catch(Exception) {}
		}

		private function auth(): bool {
			if(empty($this->driver)) return false;

			try {
				$this->driver->get("https://lk.sut.ru/");
				$this->driver->wait(10, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElements(WebDriverBy::xpath('//*[@id="users"]')) != null;
				});
			} catch(Exception) {
				return false;
			}

			$this->driver->findElement(WebDriverBy::xpath('//*[@id="users"]'))->sendKeys($this->user_login);
			$this->driver->findElement(WebDriverBy::xpath('//*[@id="parole"]'))->sendKeys($this->user_pass);
			$this->driver->findElement(WebDriverBy::xpath('//*[@id="logButton"]'))->click();

			try {
				$this->driver->wait(10, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElements(WebDriverBy::xpath('//*[@class="badge badge-secondary"]')) != null;
				});
			} catch(Exception) {
				return false;
			}

			return true;
		}

		public function getInfo(): array|false {
			if(!$this->auth()) {
				return false;
			} elseif($this->driver->findElements(WebDriverBy::xpath('//*[@class="badge badge-secondary"]')) == null) {
				return false;
			}

			try {
				$this->driver->findElement(WebDriverBy::xpath('//img[@onclick="openpage(\'profil.php\')"]'))->click();
				$this->driver->wait(3, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElements(WebDriverBy::xpath('//*[@id="rightpanel"]/table[1]/tbody/tr[2]/td')) != null;
				});

				return [
					'name' => $this->driver->findElement(WebDriverBy::xpath('//*[@id="rightpanel"]/table[1]/tbody/tr[2]/td'))->getText(),
					'birthday' => $this->driver->findElement(WebDriverBy::xpath('//*[@id="rightpanel"]/table[1]/tbody/tr[3]/td'))->getText(),
					'group' => $this->driver->findElement(WebDriverBy::xpath('//*[@id="rightpanel"]/table[2]/tbody/tr[7]/td'))->getText(),
					'cookie' => "miden=".$this->driver->manage()->getCookieNamed('miden')['value'].";uid=".$this->driver->manage()->getCookieNamed('uid')['value'],
				];
			} catch(Exception) {
				return false;
			}
		}

		public function getCookie(): ?string {
			if(!$this->auth()) {
				return null;
			}

			try { // В 4 утра умирает вебдрайвер, по причине падения ЛК, для того чтобы бот не улетал в ошибку обворачиваем данный код в try {} catch {}
				return "miden=".$this->driver->manage()->getCookieNamed('miden')['value'].";uid=".$this->driver->manage()->getCookieNamed('uid')['value'];
			} catch(Exception) {
				return null;
			}
		}

		public function getScreenMarks(): ?string {
			if(!$this->auth()) {
				return null;
			}

			try {
				$this->driver->wait(2, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElement(WebDriverBy::xpath('//*[@data-target="#collapse1"]'))->isDisplayed();
				});
				$this->driver->findElement(WebDriverBy::xpath('//*[@data-target="#collapse1"]'))->click();

				$this->driver->wait(2, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElement(WebDriverBy::xpath('//*[@key="6119"]'))->isDisplayed();
				});
				$this->driver->findElement(WebDriverBy::xpath('//*[@title="Дневник"]'))->click();

				$this->driver->wait(2, 25)->until(function(ChromeDriver $driver) {
					return $driver->findElement(WebDriverBy::xpath('//*[@id="rightpanel"]/table'))->isDisplayed();
				});

				$name = uniqid();
				$this->driver->findElement(WebDriverBy::xpath('//*[@id="rightpanel"]/table'))->takeElementScreenshot("Files/$name.png");
			} catch(Exception) {
				return null;
			}

			return "Files/$name.png";
		}


	}