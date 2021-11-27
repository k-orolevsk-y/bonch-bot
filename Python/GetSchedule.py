import sys
import time
import ujson
from datetime import date
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException

webdriver_path = "/usr/lib/chromium-browser/chromedriver"
args = sys.argv

if len(args) < 3:
    print(-1)
    exit()

if len(args) > 3:
    current_date = args[3]
else:
    current_date = date.today().strftime('%d.%m.%Y')

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
date_is_fined = False
result = []

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
            tds = tr.find_elements(value='td', by=By.TAG_NAME)
            result.append({
                'num_with_time': tds[0].text,
                'name': tds[1].text.split('\n')[0],
                'type': tds[1].text.split('\n')[1],
                'place': tds[2].text,
                'teacher': tds[3].text
            })
    except NoSuchElementException:
        continue

driver.quit()
print(ujson.encode({'date': current_date, 'count': len(result), 'items': result}))
