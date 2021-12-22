import os
import sys
import time
import uuid
import ujson
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import NoSuchElementException

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
driver.get(f"https://www.sut.ru/studentu/raspisanie/raspisanie-zanyatiy-studentov-ochnoy-i-vecherney-form-obucheniya?group={args[1]}&date={args[2]}")

time.sleep(1)
img = None

try:
    img = driver.find_element(value='vt236', by=By.CLASS_NAME).screenshot_as_png
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
