import os
import sys
import time
import uuid
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
chrome_options.add_argument("--start-maximized")
chrome_options.add_argument('window-size=1720,2880')

driver = webdriver.Chrome(webdriver_path, options=chrome_options)
driver.get("https://lk.sut.ru/")

driver.find_element(value='//*[@id="users"]', by=By.XPATH).send_keys(args[1])
driver.find_element(value='//*[@id="parole"]', by=By.XPATH).send_keys(args[2])
driver.find_element(value='//*[@id="logButton"]', by=By.XPATH).click()

time.sleep(1)
try:
    driver.find_element(value='//*[@class="badge badge-secondary"]', by=By.XPATH)
except UnexpectedAlertPresentException:
    print(0)
    driver.quit()
    exit()
except NoSuchElementException:
    print(0)
    driver.quit()
    exit()

driver.find_element(value='//*[@data-target="#collapse1"]', by=By.XPATH).click()
time.sleep(1)
driver.find_element(value='//*[@title="Дневник"]', by=By.XPATH).click()
time.sleep(1)


img = None
try:
    img = driver.find_element(value='//*[@id="rightpanel"]/table', by=By.XPATH).screenshot_as_png
except NoSuchElementException:
    pass

if img is None:
    print(ujson.encode({"error": True}))
else:
    file_name = str(uuid.uuid4()) + ".png"
    with open(f"{os.getcwd()}/Files/{file_name}", "wb") as f:
        f.write(img)
    print(ujson.encode({"error": False, "file_name": file_name}))
driver.quit()
