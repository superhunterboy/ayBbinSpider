<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Libs\Utils;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerReportTask
{
    private static $submitDatas = [];
    private static $start;
    private static $end;
    private static $hallid;
    private static $currency;
    private static $pageNum;
    private static $selectdate;
    private static $bbDate;
    private static $timeZone;
    private static $bbinSpider;
    private static $crawler;
    private static $currentime;
    private static $history;

    public static function firstSteps()
    {
        $file             = __DIR__ . '/../../data/crawlReporTime.txt';
        $retMsg           = null;
        self::$bbinSpider = BBINSpider::getInstance();
        $reportHtml       = self::$bbinSpider->getReport();
        if ($reportHtml) {
            $reportHtml = str_replace('&nbsp;', '', $reportHtml);
            $datas      = Utils::parseReportPage($reportHtml);
            if (isset($datas['hallid']) && $datas['hallid'] > 0) {
                $tmpArr           = [];
                self::$start      = $datas['start'] ?? '';
                self::$end        = $datas['end'] ?? '';
                self::$hallid     = $datas['hallid'] ?? 0;
                self::$currency   = $datas['currency'] ?? 'CNY';
                self::$pageNum    = $datas['page_num'] ?? 30;
                self::$selectdate = $datas['selectdate'] ?? 'today';
                self::$bbDate     = $datas['bb_date'] ?? '';
                self::$timeZone   = $datas['time_zone'] ?? 'est';
                // 1、0点的时候再往前一天爬一下数据
                // 2、跨天爬数据，且只爬一天的数据
                self::$currentime = self::$bbDate;
                if (is_file($file)) {
                    $tmpData = json_decode(file_get_contents($file), true);
                    $lastime = $tmpData['date'] ?? null;
                    $days    = floor((strtotime(self::$currentime) - strtotime($lastime)) / 86400);
                    if ($lastime != self::$currentime) {
                        // 昨天
                        $selectdate = 'yesterday';
                        $startime   = '00:00:00';
                        $endtime    = '23:59:59';
                        if ($days > 1) {
                            // 跨天了
                            $selectdate = 'history';
                            $startime   = $lastime;
                            $endtime    = $lastime;
                        }
                        $reportHtml = self::$bbinSpider->getTodayOrYesterdayOrHistoryReport([
                            'hallid'     => self::$hallid,
                            'selectdate' => $selectdate,
                            'start'      => $startime,
                            'end'        => $endtime,
                        ]);
                        if ($reportHtml) {
                            $reportHtml = str_replace('&nbsp;', '', $reportHtml);
                            $datas      = Utils::parseReportPage($reportHtml);
                            if (isset($datas['hallid']) && $datas['hallid'] > 0) {
                                self::$start      = $datas['start'] ?? $startime;
                                self::$end        = $datas['end'] ?? $endtime;
                                self::$hallid     = $datas['hallid'] ?? 0;
                                self::$selectdate = $datas['selectdate'] ?? $selectdate;
                                self::$bbDate     = $datas['bb_date'] ?? '';
                                self::$history    = self::$selectdate == 'yesterday' ? date('Y-m-d', strtotime(self::$currentime . ' -1 day')) : $lastime;
                            }
                        }
                    }
                }
                self::$crawler = new Crawler();
                self::$crawler->addHtmlContent($reportHtml);
                try {
                    self::$crawler->filterXPath('//table[@class="DepositTable"]/tr')->each(
                        function (Crawler $tr_node, $tr_i) use (&$tmpArr) {
                            if ($tr_i >= 1 && $tr_i <= 3) {
                                $tmpArr[$tr_i]['name']  = trim($tr_node->filterXPath('//th')->eq(0)->text());
                                $tmpArr[$tr_i]['total'] = preg_replace("/\s*/", '', $tr_node->filterXPath('//td')->eq(0)->text());
                                $flag                   = $tr_node->filterXPath('//td')->eq(0)->attr('onclick');
                                // D.show_detail('pay_company', 1);
                                if (preg_match("/.*\'([\w_]+)\'.*/", $flag, $matches)) {
                                    $flag = $matches[1];
                                }
                                $tmpArr[$tr_i]['flag'] = $flag;
                            }
                        }
                    );
                } catch (Exception $e) {
                    // echo $e->getMessage();
                    $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
                }
                if ($tmpArr) {
                    $params = [];
                    foreach ($tmpArr as $key => $val) {
                        $tmpArr[$key]['time']       = self::$selectdate == 'today' ? self::$bbDate : self::$history;
                        $params[$key]['pyte']       = $val['flag'];
                        $params[$key]['start']      = self::$start;
                        $params[$key]['end']        = self::$end;
                        $params[$key]['hallid']     = self::$hallid;
                        $params[$key]['currency']   = self::$currency;
                        $params[$key]['page_num']   = self::$pageNum;
                        $params[$key]['selectdate'] = self::$selectdate;
                        $params[$key]['bb_date']    = self::$bbDate;
                        $params[$key]['time_zone']  = self::$timeZone;
                        $params[$key]['page']       = 1;
                    }
                    if ($params) {
                        self::$submitDatas['report'] = $tmpArr;
                        self::secondSteps($params);
                        if (self::$submitDatas) {
                            $config   = require __DIR__ . '/../../config/settings.php';
                            $client   = new Client();
                            $response = $client->request('POST', $config['api']['add_reports'], [
                                'form_params' => [
                                    'jsonData' => json_encode(self::$submitDatas),
                                ],
                            ]);
                            if ($response->getStatusCode() == '200') {
                                file_put_contents($file, json_encode(['date' => self::$currentime]), LOCK_EX);
                                echo $response->getBody()->getContents();
                            }
                        }
                    }
                }
            }
        }
        echo $retMsg;
    }

    public static function secondSteps($params = [])
    {
        $datas    = [];
        $reportL1 = self::$bbinSpider->getReportL1($params);
        if ($reportL1) {
            foreach ($reportL1 as $key => $val) {
                $tmpArr = json_decode($val, true);
                if (isset($tmpArr['data']) && $tmpArr['data']) {
                    foreach ($tmpArr['data'] as $key1 => $val1) {
                        $key1                               = trim($key1, "'");
                        $datas[$key][$key1]['total_amount'] = $val1['total_amount'] ?? 0;
                        $datas[$key][$key1]['total_user']   = $val1['total_user'] ?? 0;
                        $datas[$key][$key1]['currency']     = $val1['currency'] ?? '';
                        $datas[$key][$key1]['tag']          = $val1['tag'] ?? ($val1['opcode'] ?? $key1);
                        $datas[$key][$key1]['total']        = $val1['total'];
                        $datas[$key][$key1]['text']         = $val1['text'];
                    }
                }
            }
        }
        if ($datas) {
            $params2 = [];
            foreach ($datas as $key => $val) {
                foreach ($val as $key1 => $val1) {
                    if ($val1['total']) {
                        $key1                         = $key1 . ',1';
                        $params2[$key1]['pyte']       = $key;
                        $params2[$key1]['pyte2']      = $val1['tag'];
                        $params2[$key1]['start']      = self::$start;
                        $params2[$key1]['end']        = self::$end;
                        $params2[$key1]['hallid']     = self::$hallid;
                        $params2[$key1]['currency']   = self::$currency;
                        $params2[$key1]['page_num']   = self::$pageNum;
                        $params2[$key1]['selectdate'] = self::$selectdate;
                        $params2[$key1]['bb_date']    = self::$bbDate;
                        $params2[$key1]['time_zone']  = self::$timeZone;
                        $params2[$key1]['page']       = 1;
                    }
                }
            }
            if ($params2) {
                self::$submitDatas['report_l1'] = $datas;
                self::thirdSteps($params2);
            }
        }
    }

    public static function thirdSteps($params = [])
    {
        $reportL2 = self::$bbinSpider->getReportL2($params);
        if ($reportL2) {
            $pages = [];
            foreach ($reportL2 as $key => $val) {
                $keys  = explode(',', $key);
                $type2 = $keys[1];
                // $type = $keys[0];
                // $page = $keys[2];
                $pages[$type2] = Utils::parseReportPageNum($val);
            }
            foreach ($params as $val) {
                $type2        = $val['pyte2'];
                $totalPageNum = $pages[$type2];
                if ($val['page'] < $totalPageNum) {
                    foreach (range(2, $totalPageNum) as $page) {
                        $key          = $type2 . ',' . $page;
                        $val['page']  = $page;
                        $params[$key] = $val;
                    }
                }
            }
            self::fourthSteps($params);
        }
    }

    public static function fourthSteps($params = [])
    {
        $reportL2 = self::$bbinSpider->getReportL2($params);
        if ($reportL2) {
            $tmpArr = [];
            foreach ($reportL2 as $key => $val) {
                $tmp   = [];
                $keys  = explode(',', $key);
                $type  = $keys[0];
                $type2 = $keys[1];
                $page  = $keys[2];
                if ($type == 'pay_company') {
                    $tmp = self::parsePayCompany($val);
                } elseif ($type == 'pay_online') {
                    $tmp = self::parsePayOnline($val);
                } elseif ($type == 'artificial_Deposit') {
                    $tmp = self::parseArtificialDeposit($val);
                }
                if ($tmp) {
                    $tmpArr[$type . ',' . $type2 . ',' . $page] = $tmp;
                }
            }
            if ($tmpArr) {
                self::$submitDatas['report_l2'] = $tmpArr;
            }
        }
    }

    /**
     * 公司入款
     * @param  string $html 页面内容
     * @return array
     */
    private static function parsePayCompany($html = '')
    {
        $tmpArr = [];
        self::$crawler->clear();
        self::$crawler->addHtmlContent($html);
        try {
            self::$crawler->filterXPath('//table[@id="DetailContent"]/tr')->each(
                function (Crawler $tr_node, $tr_i) use (&$tmpArr) {
                    if ($tr_i >= 2) {
                        $tr_node->filterXPath('//td[not(@*)]')->each(
                            function (Crawler $td_node, $td_i) use (&$tmpArr, $tr_i) {
                                $children      = $td_node->children();
                                $childrenCount = $children->count();
                                if ($childrenCount > 0) {
                                    $nodeName = $children->nodeName();
                                    if ($nodeName == 'table') {
                                        $children->filterXPath('//table[@class="inner_table"]/tr')->each(
                                            function (Crawler $tr1_node, $tr1_i) use (&$tmpArr, $tr_i, $td_i) {
                                                $tr1_node->filterXPath('//td[contains(@align, "left") or contains(@align, "right")]')->each(
                                                    function (Crawler $td1_node, $td1_i) use (&$tmpArr, $tr_i, $td_i, $tr1_i) {
                                                        $tmpArr[$tr_i][$td_i][$tr1_i][$td1_i] = $td1_node->text();
                                                    }
                                                );
                                            }
                                        );
                                    }
                                } else {
                                    $tmpArr[$tr_i][$td_i] = $td_node->text();
                                }
                            }
                        );
                    }
                }
            );
        } catch (Exception $e) {
            return false;
            // echo $e->getMessage();
        }
        return $tmpArr;
    }

    /**
     * 线上支付
     * @param  string $html 页面内容
     * @return array
     */
    private static function parsePayOnline($html = '')
    {
        $tmpArr = [];
        self::$crawler->clear();
        self::$crawler->addHtmlContent($html);
        try {
            self::$crawler->filterXPath('//table[@id="DetailContent"]/tr')->each(
                function (Crawler $tr_node, $tr_i) use (&$tmpArr) {
                    if ($tr_i >= 2) {
                        $tr_node->filterXPath('//td[not(@*)]')->each(
                            function (Crawler $td_node, $td_i) use (&$tmpArr, $tr_i) {
                                $tmpArr[$tr_i][$td_i] = $td_node->text();
                            }
                        );
                    }
                }
            );
        } catch (Exception $e) {
            return false;
            // echo $e->getMessage();
        }
        return $tmpArr;
    }

    /**
     * 人工存入
     * @param  string $html 页面内容
     * @return array
     */
    private static function parseArtificialDeposit($html = '')
    {
        return self::parsePayOnline($html);
    }
}
