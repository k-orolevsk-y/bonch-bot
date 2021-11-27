import sys
import time
import ujson
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException

webdriver_path = "/usr/lib/chromium-browser/chromedriver"
args = sys.argv

if len(args) < 3:
    print(-1)
    exit()
    
chrome_options = Options()
chrome_options.add_argument("--incognito")
chrome_options.add_argument("--headless")
chrome_options.add_argument('--no-sandbox')

driver = webdriver.Chrome(webdriver_path, options=chrome_options)
driver.get("https://lk.sut.ru/")

driver.find_element(value='//*[@id="users"]', by=By.XPATH).send_keys(args[1])
driver.find_element(value='//*[@id="parole"]', by=By.XPATH).send_keys(args[2])
driver.find_element(value='//*[@id="logButton"]', by=By.XPATH).click()

time.sleep(1)
try:
    driver.find_element(value='//img[@onclick="openpage(\'profil.php\')"]', by=By.XPATH).click()
    time.sleep(1)

    print(ujson.encode({
        "name": driver.find_element(value='//*[@id="rightpanel"]/table[1]/tbody/tr[2]/td', by=By.XPATH).text,
        "birthday": driver.find_element(value='//*[@id="rightpanel"]/table[1]/tbody/tr[3]/td', by=By.XPATH).text,
        "group": driver.find_element(value='//*[@id="rightpanel"]/table[2]/tbody/tr[7]/td', by=By.XPATH).text,
    }))
    driver.quit()
except UnexpectedAlertPresentException:
    print(0)
    driver.quit()
except NoSuchElementException:
    print(0)
    driver.quit()
