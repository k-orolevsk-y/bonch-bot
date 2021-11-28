import sys
import time
import ujson
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
<<<<<<< HEAD
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException
=======
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait, Select
>>>>>>> origin/master

webdriver_path = "/usr/lib/chromium-browser/chromedriver"
args = ujson.decode(" ".join(sys.argv[1:]).replace('\'', '"'))

need_args = ['login', 'pass', 'why', 'goal']
for arg in need_args:
    if arg not in args:
        print(0)
        exit()

chrome_options = Options()
chrome_options.add_argument("--incognito")
chrome_options.add_argument("--headless")
chrome_options.add_argument('--no-sandbox')

driver = webdriver.Chrome(webdriver_path, options=chrome_options)
driver.get("https://lk.sut.ru/")

driver.find_element(value='//*[@id="users"]', by=By.XPATH).send_keys(args['login'])
driver.find_element(value='//*[@id="parole"]', by=By.XPATH).send_keys(args['pass'])
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

WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="heading2"]/h5/div'))).click()
WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="menu_li_801"]'))).click()
try:
    if args['why'] == "Другое":
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="chinoe"]'))).click()
    else:
        Select(WebDriverWait(driver, 1).until(
            EC.visibility_of_element_located((By.XPATH, '//*[@id="org"]')))).select_by_visible_text(args['why'])
    if args['goal'] != "":
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="cel"]'))).send_keys(
            args['goal'])
    WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="rightpanel"]/form/input'))).click()
except Exception as e:
    driver.quit()
    print(-1)
    exit()

print(1)
driver.quit()
