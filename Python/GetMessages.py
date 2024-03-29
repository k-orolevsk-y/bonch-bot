import sys
import time
import ujson
from datetime import datetime
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
    driver.find_element(value='//*[@class="badge badge-secondary"]', by=By.XPATH)
except UnexpectedAlertPresentException:
    print(0)
    driver.quit()
    exit()
except NoSuchElementException:
    print(0)
    driver.quit()
    exit()

driver.find_element(value='//*[@title="Сообщения"]', by=By.XPATH).click()
time.sleep(1)

messages = []
my_messages = None

while True:
    table = driver.find_element(value='//*[@id="mytable"]/tbody', by=By.XPATH).find_elements(value='tr', by=By.XPATH)
    for tr in table:
        try:
            _id = str(tr.get_attribute('id')).replace('tr_', '')
            if _id.startswith("show"):
                continue

            tds = tr.find_elements(value="td", by=By.TAG_NAME)
            tds[1].click()

            messages.append({
                'id': int(_id),
                'date': datetime.strptime(tds[0].text, "%d-%m-%Y %H:%M:%S").timestamp(),
                'title': tds[1].text,
                "sender" if my_messages is None else "receiver": tds[3].text.replace(' (сотрудник/преподаватель)', ''),
                'files': [a.get_attribute('href') for a in tds[2].find_elements(value='a', by=By.TAG_NAME)]
            })
        except NoSuchElementException:
            continue

    for key in range(len(messages)):
        try:
            messages[key]['text'] = driver.find_element(value=f'//*[@id="annotation_{messages[key]["id"]}"]', by=By.XPATH).text
        except NoSuchElementException:
            messages[key]['text'] = ""
            continue

    time.sleep(0.1)
    button = driver.find_elements(value="Следующая", by=By.LINK_TEXT)

    if len(button) < 1:
        if my_messages is not None:
            break

        my_messages = driver.find_element(value='//*[@id="block_content"]/div[2]/a', by=By.XPATH)
        my_messages.click()

        time.sleep(1)
        continue
    else:
        button[0].click()
    time.sleep(1)

messages.sort(key=lambda msg: msg['date'])
messages.reverse()

result = {}
for message in messages:
    if message.get('sender') is not None:
        key = message['sender']
    elif message.get('receiver') is not None:
        key = message['receiver']
    else:
        key = "Неотсортированные"

    if result.get(key) is None:
        result[key] = []
    result[key].append(message)


print(ujson.encode({'count': len(messages), 'sorted_messages': result, 'only_messages': messages}))
driver.quit()
