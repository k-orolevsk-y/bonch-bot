import sys
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options

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
    print(driver.get_cookie('miden')['value'])
except Exception:
    print(0)
driver.close()
