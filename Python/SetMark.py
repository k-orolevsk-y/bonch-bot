import sys
import time
from datetime import date
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException

webdriver_path = "/usr/lib/chromium-browser/chromedriver"
args = sys.argv

if len(args) < 4:
    exit("-1")

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

driver.find_element(value='//*[@data-target="#collapse1"]', by=By.XPATH).click()
time.sleep(0.5)
driver.find_element(value='//*[@title="Расписание"]', by=By.XPATH).click()
time.sleep(1.5)

table = driver.find_element(value='//table[@class="simple-little-table"]/tbody', by=By.XPATH).find_elements(value='tr', by=By.XPATH)
current_date = date.today().strftime('%d.%m.%Y')
date_is_fined = False
is_marking = False

for tr in table:
    try:
        is_date = tr.get_attribute('style') == 'background: rgb(179, 179, 179);'
        if is_date:
            if date_is_fined:
                break

            date = tr.text.split('\n')[1]
            if date == current_date:
                date_is_fined = True
                continue

        if date_is_fined:
            name_with_time = tr.find_elements(value='td', by=By.TAG_NAME)[0].text.replace(' ', '').replace('(', '').replace(')', '')
            if name_with_time == args[3]:
                text_column = str(tr.find_elements(value='td', by=By.TAG_NAME)[4].text)
                if text_column.find("ждем начала") != -1:
                    print(-2)
                    driver.quit()
                    exit()

                current_started = text_column.find("Кнопка появится") == -1 and text_column.find('Начать занятие') == -1
                if current_started:
                    print(-3)
                    driver.quit()
                    exit()

                tr.find_element(value="Начать занятие", by=By.LINK_TEXT).click()
                is_marking = True
    except NoSuchElementException:
        continue

driver.quit()
if is_marking:
    print(1)
else:
    print(-2)
