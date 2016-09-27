#-*_coding:utf8-*-
import requests
import re
import sys
reload(sys)
sys.setdefaultencoding("utf-8")

class spider(object):
    def __init__(self):
        print u'开始爬取内容'
    def getsource(self,url):
        html = requests.get(url)
        return html.text

    def changepage(self,url,total_page):
        now_page = int(re.search('pi=(\d)', url).group(1))
        page_group = []
        for i in range(now_page,total_page+1):
            link = re.sub('pi=\d','pi=%s'%i,url,re.S)
            page_group.append(link)
        return page_group

    def geteveryapp(self,source):
        everyapp = re.findall('(<li class="list_item".*?</li>)',source,re.S)
        return everyapp

    def getinfo(self,eachclass):
        info = {}
        str1 = str(re.search('<a href="(.*?)">', eachclass).group(0))
        app_url = re.search('"(.*?)"', str1).group(1)
        appdown_url = app_url.replace('appinfo', 'appdown')
        info['app_url'] = appdown_url
        print appdown_url
        return info

    def saveinfo(self,classinfo):
        f = open('info.txt','a')
        str2 = "http://apk.hiapk.com"
        for each in classinfo:
            f.write(str2)
            f.writelines(each['app_url'] + '\n')
        f.close()

if __name__ == '__main__':

    appinfo = []
    url = 'http://apk.hiapk.com/apps/MediaAndVideo?sort=5&pi=1'
    appurl = spider()
    all_links = appurl.changepage(url, 5)
    for link in all_links:
        print u'正在处理页面' + link
        html = appurl.getsource(link)
        every_app = appurl.geteveryapp(html)
        for each in every_app:
            info = appurl.getinfo(each)
            appinfo.append(info)
    appurl.saveinfo(appinfo)