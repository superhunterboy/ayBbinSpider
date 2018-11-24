<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Libs\Request;
use Weiming\Libs\Utils;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerTask
{

    public static function crawlCashWithDrawal()
    {
        //file_put_contents(__DIR__.'/../../data/'.date('Y-m-d').'crawlCashWithDrawal.log',date('H:i:s')."\n",FILE_APPEND);
        $retMsg = null;
        // 列表页
        $html = BBINSpider::getInstance()->cashWithDrawal();
        $html = minify_html(minify_js($html));
        // var_dump($html);
        // 登录后的情况
        if (!empty($html) && strpos($html, '重新登入') === false && $html != "<script>window.open('http://js168.9661p.com','_top')</script>" && strpos($html, '维护通知') === false && strpos($html, '维护公告') === false) {
            $tableData       = [];
            $searchtml       = null;
            $module          = null;
            $hall_id         = null;
            $MaxWithdrawalID = null;
            $crawler         = new Crawler();
            $crawler->addHtmlContent($html);
            try {
                $module          = $crawler->filterXPath('//input[@type="hidden" and @name="module"]')->attr('value');
                $hall_id         = $crawler->filterXPath('//input[@type="hidden" and @name="hall_id"]')->attr('value');
                $MaxWithdrawalID = $crawler->filterXPath('//input[@type="hidden" and @name="MaxWithdrawalID"]')->attr('value');
            } catch (Exception $e) {
                $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
            }
            if ($module == 'CashWithdrawal' && $hall_id && $MaxWithdrawalID) {
                // 只查询 0 处理中 包含 未处理、已锁定两种情况
                $searchtml = BBINSpider::getInstance()->SearchCashWithDrawal(['hall_id' => $hall_id, 'MaxWithdrawalID' => $MaxWithdrawalID]);
                // var_dump($searchtml);
            }
            if ($searchtml) {
                $config  = require __DIR__ . '/../../config/settings.php';
                $crawler = new Crawler();
                $crawler->addHtmlContent($searchtml);
                try {
                    $crawler->filterXPath('//form[contains(@name, "IPL_Form") and @name!="IPL_Form"]')->each(function (Crawler $tr_node, $tr_i) use (&$tableData, $config) {
                        $tr_node->filterXPath('//tr/td')->each(function (Crawler $td_node, $td_i) use (&$tableData, $tr_i, $config) {
                            if ($td_i == 9) {
                                $memberInfo                        = $td_node->filterXPath('//div')->attr('onclick');
                                $memberInfo                        = explode("','", substr($memberInfo, 15, strlen($memberInfo) - 24));
                                $memberId                          = $memberInfo[0];
                                $member                            = json_decode(BBINSpider::getInstance()->getMemberInfo($memberId), true); // <script>window.open('http://js168.9661p.com','_top')</script>
                                $member['tel']                     = Utils::desecbEncrypt($member['tel'], $config['key']);
                                $member['account']                 = Utils::desecbEncrypt($member['account'], $config['key']);
                                $tableData[$tr_i][$td_i]['id']     = $memberId;
                                $tableData[$tr_i][$td_i]['member'] = $member; // 会员信息
                                $tableData[$tr_i][$td_i]['value']  = $td_node->filterXPath('//div/font')->text(); // 出款资讯
                            } elseif ($td_i == 16) {
                                // 三种状态：1 xxx已锁定（不是本人操作） 2 锁定，确定，取消，拒绝 3 解锁，确定，取消，拒绝
                                $status = trim($td_node->filterXPath('//div')->text());
                                if (!in_array($status, ['确定', '取消', '拒绝']) && strpos($status, '已锁定') === false) {
                                    $btn1 = $td_node->filterXPath('//div/input')->eq(0)->attr('value'); // 解锁 or 锁定
                                    // $btn2 = $td_node->filterXPath('//div/input')->eq(1)->attr('value');  // 确定
                                    // $btn3 = $td_node->filterXPath('//div/input')->eq(2)->attr('value');  // 取消
                                    // $btn4 = $td_node->filterXPath('//div/input')->eq(3)->attr('value');  // 拒绝
                                    if ($btn1 == '解锁') {
                                        $status = '已锁定';
                                    } elseif ($btn1 == '锁定') {
                                        $status = '未处理';
                                    }
                                }
                                $tableData[$tr_i][$td_i] = $status;
                            } else {
                                $tableData[$tr_i][$td_i] = trim($td_node->filterXPath('//div')->text());
                            }
                        });
                    });
                } catch (Exception $e) {
                    $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
                }
                if ($tableData) {
                    /**
                     *   Array
                     *   (
                     *       [0] => 206839557                                          // 出款记录ID
                     *       [1] => 澳亞國際                                           // 站别
                     *       [2] => ▲总存款【1千】                                     // 层级
                     *       [3] => ajs8888                                            // 大股东账号
                     *       [4] => drita888                                           // 代理商账号
                     *       [5] => ww1985                                             // 会员账号
                     *       [6] => 2710                                               // 提出额度
                     *       [7] => 0                                                  // 手续费
                     *       [8] => 0                                                  // 优惠金额
                     *       [9] => Array
                     *           (
                     *               [id] => 206839557                                 // 出款记录ID
                     *               [member] => Array
                     *                           (
                     *                               [account_name] => 陈琪锋          // 户名
                     *                               [bank] => 农业银行                // 银行名称
                     *                               [account] => 6228480372192617515  // 银行账号
                     *                               [province] => 浙江省              // 省份
                     *                               [city] => 绍兴市                  // 城市
                     *                               [tel] => 18368520213              // 手机
                     *                               [detail_modified] =>              // 真实姓名有异动
                     *                               [note] => 已核实 Queenie          // 备注
                     *                           )
                     *
                     *               [value] => 2710                                   // 出款资讯
                     *           )
                     *
                     *       [10] => 首次出款                                          // 出款状况
                     *       [11] => 否                                                // 优惠扣除
                     *       [12] => 0                                                 // 支付平台手续费
                     *       [13] => 2710                                              // 实际出款金额
                     *       [14] => 请选择                                            // 出款商号
                     *       [15] => 2017-09-27 14:26:47                               // 出款日期
                     *       [16] => 确定                                              // 已出款，锁定/解锁、确定、取消、拒绝
                     *       [17] => 2ruben                                            // 操作者
                     *       [18] => 无人接听                                          // 备注
                     *       [19] => 2017-09-27 14:40:42                               // 最后异动时间
                     *   )
                     */
                    $client   = new Client();
                    $response = $client->request('POST', $config['api']['add_pay_out'], [
                        'form_params' => [
                            'jsonData' => json_encode($tableData),
                        ],
                    ]);
                    if ($response->getStatusCode() == '200') {
                        $retMsg = $response->getBody()->getContents();
                    }
                }
            }
        } else {
            $otpFile = __DIR__ . '/../../data/otp.txt';
            if (is_file($otpFile)) {
                $otpVal = file_get_contents($otpFile);
                if ($otpVal) {
                    // 访问登录页面
                    $loginBefore     = BBINSpider::getInstance()->loginBefore();
                    $loginBeforeHtml = minify_html(minify_js($loginBefore));
                    // 可以正常登录的情况
                    if (!empty($loginBeforeHtml) && strpos($loginBeforeHtml, '维护通知') === false && strpos($loginBeforeHtml, '维护公告') === false) {
                        $lang     = null;
                        $username = null;
                        $password = null;
                        $otp      = null;
                        $crawler  = new Crawler();
                        $crawler->addHtmlContent($loginBeforeHtml);
                        try {
                            $lang     = $crawler->filterXPath('//*[@id="lang"]')->attr('name');
                            $username = $crawler->filterXPath('//*[@id="username"]')->attr('name');
                            $password = $crawler->filterXPath('//*[@id="passwd"]')->attr('name');
                            $otp      = $crawler->filterXPath('//*[@id="OTP"]')->attr('name');
                        } catch (Exception $e) {
                            $retMsg = Utils::sendInnerMessage(['ret' => 1003, 'msg' => '爬虫无法识别页面元素']);
                        }
                        if ($lang == 'lang' && $username == 'username' && $password = 'passwd' && $otp == 'OTP') {
                            // 模拟登录
                            $retHtml = BBINSpider::getInstance()->login($otpVal);
                            //var_dump($retHtml);
                            // {"code":200,"status":"OK","data":{"result":true,"session_id":"865d553b27eb3995383cdef77e603a65a640b425","redirect":"\/user\/home\/note"}}
                            // {"code":200,"status":"OK","data":{"result":false,"message":"OTP\u5bc6\u7801\u9519\u8bef\uff01\u8bf7\u8f93\u5165\u6b63\u786e\u5bc6\u7801","n":420}}
                            $retJson = json_decode($retHtml, true);
                            if ($retJson && $retJson['data']['result']) {
                                // var_dump(BBINSpider::getInstance()->cashWithDrawal());
                                Utils::sendInnerMessage(['ret' => 1006, 'msg' => 'OTP密码正确，谢谢！']);
                            } else {
                                Utils::sendInnerMessage(['ret' => 1001, 'msg' => 'OTP密码错误，请输入正确OTP密码！']);
                            }
                        }
                    }
                    else    // 不能正常登录的情况（登录页面打不开，如系统维护的情况）
                    {
                        Utils::sendInnerMessage(['ret' => 1002, 'msg' => 'BBIN系统维护中！']);
                    }
                }
                //unlink($otpFile);
            } else {
                // 该登录了，建立socket连接到内部推送端口
                $retMsg = Utils::sendInnerMessage(['ret' => 1001, 'msg' => '需要输入OTP登录']);
            }
        }
        echo $retMsg;
    }
}
