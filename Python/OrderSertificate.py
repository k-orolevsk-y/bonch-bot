import sys
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait, Select

webdriver_path = "/usr/lib/chromium-browser/chromedriver"
args = sys.argv

if len(args) < 5:
    print(-1)
    exit()
# args[4] - куда справка, инпуты: СПб ГКУ «Организатор перевозок», Место работы родителей, Налоговая инспекция (ФНС, УФНС, ИФНС), Территориальный орган Пенсионного фонда РФ, Место работы, Другое
# args[5] - цель получения, инпуты - любая строка, если без цели - ничего
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
    if args[4] == "Другое":
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="chinoe"]'))).click()
    else:
        Select(WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="org"]')))).select_by_visible_text(args[4])
    if args[5] != "":
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="cel"]'))).send_keys(args[5])
    WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="rightpanel"]/form/input'))).click()
except Exception as e:
    print("exception:" + e)  #!Обработчик ошибок у тебя свой вроде бы
finally:
    print("успешно") # мб юзеру написать, что через 3 рабочих дня готово будет, не забудь забрать


