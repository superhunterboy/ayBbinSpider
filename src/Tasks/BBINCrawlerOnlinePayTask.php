<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerOnlinePayTask
{
    public static function crawlOnlinePay()
    {
        $retMsg = 'no data';
        // 列表页
        $config  = require __DIR__ . '/../../config/settings.php';
        $html = BBINSpider::getInstance()->showOnlinePayOrderPage();
        $html = minify_html(minify_js($html));
        // var_dump($html);
        // 登录后的情况
        if (!empty($html) && strpos($html, 'System Error') === false && $html != "<script>window.open('".str_replace('https', 'http', $config['bbin']['domain'])."','_top')</script>" && strpos($html, '维护通知') === false && strpos($html, '维护公告') === false) {
            $crawler = new Crawler();
            $crawler->addHtmlContent($html);

            $hall_id = $crawler->filterXPath('//input[@type="hidden" and @name="hall_id"]')->attr('value');
            // 获取今日此时的累计分页数
            $maxPageNum = $crawler->filterXPath('//select[@id="page_option"]/option[last()]')->attr('value');
            $payOrderFile = __DIR__ . '/../../data/payOrderRecord.txt';
            $pageOption = $maxPageNum;   // 从第几页开始抓取
            $startDate = date('Y-m-d 00:00:00');
            $endDate = date('Y-m-d 23:59:59');
            if (file_exists($payOrderFile)) {
                $contents = file_get_contents($payOrderFile);
                $aContents = json_decode($contents, true);
                $pageFetched = $aContents['pageFetched'];
                if ($aContents['lastDate'] < date('Y-m-d')) //如果是新一天的第一次抓取
                {
                    $pageFetched = 1;
                } else    //正在抓取当日数据
                {
                    $pageOption = $maxPageNum - $pageFetched;  //当前总页数-已经取了的页数
                    if ($pageOption <= 0) {
                        $pageOption = 1;
                    } else {
                        $pageFetched++;
                    }
                }
            } else {
                $pageFetched = 1;   // 某日已经抓取的页面数
            }
            $contents = ['lastDate' => date('Y-m-d'), 'pageFetched' => $pageFetched];
            $params = [
                'hall_id' => $hall_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'page_option' => $pageOption,
                'page_row' => '50',
                'MaxID' => '',
                'user_name' => '',
                'amount_min' => '',
                'amount_max' => '',
                'transfer_level' => 7489,
                'pay_no' => '',
                'status' => 'Y',
                'currency' => 'RMB',
                'pay_method' => '1',
                'payment_vendor' => 'all',
                'store_status' => '1',
                'pay_system' => '105278',
                'auto_update' => '0',
            ];

            $searchtml = BBINSpider::getInstance()->searchOnlinePayOrder($params);
            //file_put_contents(__DIR__ . '/../../data/onlinePayOrder.html',$searchtml);
            if ($searchtml) {
                $tableData = [];
                $crawler = new Crawler();
                $crawler->addHtmlContent($searchtml);
                try {
                    $crawler->filterXPath('//div/table[@id="storeTopMenu"]/tr')->each(function (Crawler $tr_node, $tr_i) use (&$tableData) {
                        $tr_node->filterXPath('//td')->each(function (Crawler $td_node, $td_i) use (&$tableData, $tr_i) {
                            if ($td_i == 3 || $td_i == 6) {
                                $tableData[$tr_i][$td_i] = '人民币';
                            } elseif ($td_i == 10) {
                                $status = $td_node->text();
                                if ($status == '成功') $status = 1;
                                else    $status = 0;
                                $tableData[$tr_i][$td_i] = $status;
                            } else {
                                $tableData[$tr_i][$td_i] = $td_node->text();
                            }
                        });
                    });
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/../../data/onlinePayOrderException.txt', '页面解析异常');
                }

                if ($tableData) {
                    /**
                     *   Array
                        (
                            [0] => 201903221360520112
                            [1] => -
                            [2] => qyk688688
                            [3] => 人民币
                            [4] => 单笔1万
                            [5] => 2019-03-22 00:00:42
                            [6] => 人民币
                            [7] => 二维支付
                            [8] => 5000
                            [9] => 5000
                            [10] => 1
                            [11] => 迅捷付 (迅捷（5568）)
                            [12] =>
                            [13] =>
                            [14] =>
                        )
                     */
                    array_pop($tableData);
                    array_pop($tableData);
                    //print_r($tableData);die;
                    $client = new Client();
                    $response = $client->request('POST', $config['api']['add_online_pay'], [
                        'form_params' => [
                            'jsonData' => json_encode($tableData),
                        ],
                    ]);
                    if ($response->getStatusCode() == '200') {
                        $retMsg = $response->getBody()->getContents();
                        file_put_contents($payOrderFile, json_encode($contents));
                    }
                }
            }

        }
        echo $retMsg;
     }
}
