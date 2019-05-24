<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Libs\Utils;
use Weiming\Spiders\BBINSpider;

class TestBBINCrawlerTask
{

    public static function doTask($otp)
    {
        $retMsg = null;
        // 列表页
        $config  = require __DIR__ . '/../../config/settings.php';
        $html = BBINSpider::getInstance()->showOnlinePayOrderPage();
        $html = minify_html(minify_js($html));
        // var_dump($html);
        // 登录后的情况
        if (!empty($html) && strpos($html, 'System Error') === false && $html != "<script>window.open('".str_replace('https', 'http', $config['bbin']['domain'])."','_top')</script>" && strpos($html, '维护通知') === false && strpos($html, '维护公告') === false) {
            $crawler         = new Crawler();
            $crawler->addHtmlContent($html);

            $hall_id         = $crawler->filterXPath('//input[@type="hidden" and @name="hall_id"]')->attr('value');
            // 获取今日此时的累计分页数
            $maxPageNum      = $crawler->filterXPath('//select[@id="page_option"]/option[last()]')->attr('value');
            $payOrderFile    = __DIR__ . '/../../data/payOrderRecord.txt';
            $pageOption      = $maxPageNum;   // 从第几页开始抓取
            $startDate       = date('Y-m-d 00:00:00');
            $endDate         = date('Y-m-d 23:59:59');
            if (file_exists($payOrderFile))
            {
                $contents = file_get_contents($payOrderFile);
                $aContents = json_decode($contents, true);
                $pageFetched = $aContents['pageFetched'];
                if ($aContents['lastDate'] < date('Y-m-d')) //如果是新一天的第一次抓取
                {
                    $pageFetched = 1;
                }
                else    //正在抓取当日数据
                {
                    $pageOption = $maxPageNum - $pageFetched;  //当前总页数-已经取了的页数
                    if($pageOption <= 0)
                    {
                        $pageOption = 1;
                    }
                    else
                    {
                        $pageFetched++;
                    }
                }
            }
            else
            {
                $pageFetched = 1;   // 某日已经抓取的页面数
            }
            $contents = ['lastDate' => date('Y-m-d'),'pageFetched' => $pageFetched];
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
            //file_put_contents('/home/hunter/Desktop/onlinePayOrder.html',$searchtml);
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
                                if($status=='成功')     $status = 1;
                                else    $status = 0;
                                $tableData[$tr_i][$td_i] = $status;
                            } else {
                                $tableData[$tr_i][$td_i] = $td_node->text();
                            }
                        });
                    });
                } catch (Exception $e) {
                    $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
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
                    $client   = new Client();
                    $response = $client->request('POST', $config['api']['add_online_pay'], [
                        'form_params' => [
                            'jsonData' => json_encode($tableData),
                        ],
                    ]);
                    if ($response->getStatusCode() == '200') {
                        $retMsg = $response->getBody()->getContents();
                        file_put_contents($payOrderFile,json_encode($contents));
                    }
                }
            }
        } else {
            $otpFile = __DIR__ . '/../../data/otp.txt';
            if (is_file($otpFile)) {
                $otpVal = $otp;
                if ($otpVal) {
                    // 访问登录页面
                    $loginBefore     = BBINSpider::getInstance()->loginBefore();
                    $loginBeforeHtml = minify_html(minify_js($loginBefore));
                    // 可以正常登录的情况
                    if (!empty($loginBeforeHtml) && strpos($loginBeforeHtml, '维护通知') === false && strpos($loginBeforeHtml, '维护公告') === false) {
//                        $lang     = null;
//                        $username = null;
//                        $password = null;
//                        $otp      = null;
//                        $crawler  = new Crawler();
//                        $crawler->addHtmlContent($loginBeforeHtml);
//                        try {
//                            $lang     = $crawler->filterXPath('//*[@id="lang"]')->attr('name');
//                            $username = $crawler->filterXPath('//*[@id="username"]')->attr('name');
//                            $password = $crawler->filterXPath('//*[@id="passwd"]')->attr('name');
//                            $otp      = $crawler->filterXPath('//*[@id="OTP"]')->attr('name');
//                        } catch (Exception $e) {
//                            $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
//                        }
//                        if ($lang == 'lang' && $username == 'username' && $password = 'passwd' && $otp == 'OTP') {
                            // 模拟登录
                            $retHtml = BBINSpider::getInstance()->login($otpVal);
                            //var_dump($retHtml);
                            // {"code":200,"status":"OK","data":{"result":true,"session_id":"865d553b27eb3995383cdef77e603a65a640b425","redirect":"\/user\/home\/note"}}
                            // {"code":200,"status":"OK","data":{"result":false,"message":"OTP\u5bc6\u7801\u9519\u8bef\uff01\u8bf7\u8f93\u5165\u6b63\u786e\u5bc6\u7801","n":420}}
                            $retJson = json_decode($retHtml, true);

                            if ($retJson && $retJson['result']) {   // 如果登录成功
                                $cookieFile = __DIR__ . '/../../data/cookies.txt';
                                $cookies = file_get_contents($cookieFile);
                                $aCookies = json_decode($cookies, true);
                                foreach ($aCookies as $k=>$item)
                                {
                                    if(in_array($item['Name'],['sid','lang','langcode','langx']))
                                    {
                                        unset($aCookies[$k]);
                                    }
                                }
                                $sDomain = str_replace('https://', '', $config['bbin']['domain']);
                                $aCookies[] = [
                                    'Name' =>'sid',
                                    'Value'=>$retJson['data']['session_id'],
                                    'Domain'=>$sDomain,
                                    'Path'=>'/',
                                    'Max-Age'=>NULL,
                                    'Expires'=>NULL,
                                    'Secure'=>NULL,
                                    'Discard'=>NULL,
                                    'HttpOnly'=>false,
                                ];
                                $aCookies[] = [
                                    'Name' =>'lang',
                                    'Value'=>'zh-cn',
                                    'Domain'=>$sDomain,
                                    'Path'=>'/',
                                    'Max-Age'=>NULL,
                                    'Expires'=>NULL,
                                    'Secure'=>NULL,
                                    'Discard'=>NULL,
                                    'HttpOnly'=>false,
                                ];
                                $aCookies[] = [
                                    'Name' =>'langcode',
                                    'Value'=>'zh-cn',
                                    'Domain'=>$sDomain,
                                    'Path'=>'/',
                                    'Max-Age'=>NULL,
                                    'Expires'=>NULL,
                                    'Secure'=>NULL,
                                    'Discard'=>NULL,
                                    'HttpOnly'=>false,
                                ];
                                $aCookies[] = [
                                    'Name' =>'langx',
                                    'Value'=>'zh-cn',
                                    'Domain'=>$sDomain,
                                    'Path'=>'/',
                                    'Max-Age'=>NULL,
                                    'Expires'=>NULL,
                                    'Secure'=>NULL,
                                    'Discard'=>NULL,
                                    'HttpOnly'=>false,
                                ];
                                file_put_contents($cookieFile,json_encode($aCookies));

                                //Utils::sendInnerMessage(['ret' => 1006, 'msg' => 'OTP密码正确，谢谢！']);
                            } else {
                                if($retJson['message'] == 'In maintenance')
                                {
                                    Utils::sendInnerMessage(['ret' => 1002, 'msg' => 'BBIN系统维护中！']);
                                }
                                else Utils::sendInnerMessage(['ret' => 1001, 'msg' => 'OTP密码错误，请输入正确OTP密码！']);
                            }
                        echo $retHtml;die;
                        //}
                    }
                    else    // 不能正常登录的情况（登录页面打不开，如系统维护的情况）
                    {
                        //Utils::sendInnerMessage(['ret' => 1002, 'msg' => 'BBIN系统维护中！']);
                    }
                }
                //unlink($otpFile);
            } else {
                // 该登录了，建立socket连接到内部推送端口
                //$retMsg = Utils::sendInnerMessage(['ret' => 1001, 'msg' => '需要输入OTP登录']);
            }
        }
        echo $retMsg;
    }
}
