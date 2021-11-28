import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.options import Options
from selenium.common.exceptions import UnexpectedAlertPresentException, NoSuchElementException
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC

webdriver_path = "/usr/lib/chromium-browser/chromedriver"


chrome_options = Options()
chrome_options.add_argument("--incognito")
chrome_options.add_argument("--headless")
chrome_options.add_argument('--no-sandbox')


def getTeachers(login, password):
    driver = webdriver.Chrome(webdriver_path, options=chrome_options)
    driver.get("https://lk.sut.ru/")

    driver.find_element(value='//*[@id="users"]', by=By.XPATH).send_keys(login)
    driver.find_element(value='//*[@id="parole"]', by=By.XPATH).send_keys(password)
    driver.find_element(value='//*[@id="logButton"]', by=By.XPATH).click()

    time.sleep(1)
    try:
        driver.find_element(value='//*[@class="badge badge-secondary"]', by=By.XPATH)
    except UnexpectedAlertPresentException:
        print(0)
        driver.quit()
        return 0
    except NoSuchElementException:
        print(0)
        driver.quit()
        return 0

    try:
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="menu_li_840"]'))).click()
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="block_content"]/a'))).click()
        optionsList = []
        options = Select(WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="frm"]/table/tbody/tr[6]/td[2]/select')))).options
        for i in range(1, len(options)):
            optionsList.append(options[i].text)
        print(optionsList)
    except Exception as e:
        print("exception:" + e)  #!Обработчик ошибок у тебя свой вроде бы
        driver.quit()
        return 0
    else:
        driver.quit()
        return optionsList # Возвращает массив с преподами

def checkUploadFile(driver):
    for i in range(0, 5):
        if driver.find_element(By.XPATH, '//*[@id="list_files"]').text != "":
            return True
        else:
            time.sleep(1)
    return False


def sendMessage(login, password, files, theme, text, teacher): # files - путь к файлу, theme, text - любая строка, если без них, то пустая, teacher - исходя из ретурна getTeachers()
    driver = webdriver.Chrome(webdriver_path, options=chrome_options)
    driver.get("https://lk.sut.ru/")

    driver.find_element(value='//*[@id="users"]', by=By.XPATH).send_keys(login)
    driver.find_element(value='//*[@id="parole"]', by=By.XPATH).send_keys(password)
    driver.find_element(value='//*[@id="logButton"]', by=By.XPATH).click()

    time.sleep(1)
    try:
        driver.find_element(value='//*[@class="badge badge-secondary"]', by=By.XPATH)
    except UnexpectedAlertPresentException:
        print(0)
        driver.quit()
        return 0
    except NoSuchElementException:
        print(0)
        driver.quit()
        return 0

    try:
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="menu_li_840"]'))).click()
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="block_content"]/a'))).click()
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="userfile"]'))).send_keys(files)
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="upload"]'))).click()
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="title"]'))).send_keys(theme)
        WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="frm"]/table/tbody/tr[4]/td/div[2]/div'))).send_keys(text)
        Select(WebDriverWait(driver, 1).until(EC.visibility_of_element_located((By.XPATH, '//*[@id="frm"]/table/tbody/tr[6]/td[2]/select')))).select_by_visible_text(teacher)

    except Exception as e:
        print("exception:" + e)  #!Обработчик ошибок у тебя свой вроде бы
        driver.quit()
        return 0
    else:
        if checkUploadFile(driver):
            driver.find_element(By.XPATH, '//*[@id="okk"]').click()
            driver.quit()
            return 1 # Все отправилось
        error = driver.find_element(By.XPATH, '//*[@id="res"]').text # если файл не загрузился - то вернет причину (ну то что красным цветом в лк)
        driver.quit()
        return error
    

