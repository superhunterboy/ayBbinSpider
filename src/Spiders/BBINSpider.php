<?php

namespace Weiming\Spiders;

use Weiming\Libs\Request;
use Weiming\Libs\Utils;

class BBINSpider
{
    private $config;

    private $request = null;

    private $bbinUrl = 'https://js168.9661p.com'; // https://js168.9661i.com

    private static $_instance = null;

    private function __construct()
    {
        $this->request = Request::getInstance();
        $this->config  = require __DIR__ . '/../../config/settings.php';
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (is_null(self::$_instance) || isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 登录澳亚 BBIN
     * @param  String $otp
     * @return String
     */
    public function login($otp)
    {
        return $this->request->send(
            $this->bbinUrl . '/user/login',
            [
                'form_params' => [
                    'lang'     => 'zh-cn',
                    'username' => $this->config['bbin']['username'],
                    'passwd'   => $this->config['bbin']['password'],
                    'OTP'      => $otp,
                ],
            ],
            'POST',
            [
                'Referer'          => $this->bbinUrl,
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 第一页出款记录，抓取参数，为查询所用
     * @return String
     */
    public function cashWithDrawal()
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/app/IPLCashSystem.php',
            ]
        );
    }

    /**
     * 根据条件查询出款记录
     * @param array $params 查询参数
     */
    public function SearchCashWithDrawal($params = [])
    {
        $MaxWithdrawalID = $params['MaxWithdrawalID'];
        $hall_id         = $params['hall_id'];
        $rows            = $params['rows'] ?? 300;
        $date_start      = $params['date_start'] ?? date("Y-m-d H:i:s", strtotime("-7 day"));
        $date_end        = $params['date_end'] ?? date("Y-m-d H:i:s", strtotime("+1 hours"));
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal&retime=0&status[]=0&currency=RMB&upperid=alln&rows=' . $rows . '&MaxWithdrawalID=' . $MaxWithdrawalID . '&hall_id=' . $hall_id . '&date_start=' . $date_start . '&date_end=' . $date_end,
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
            ]
        );
    }

    /**
     * 爬取会员信息，一次抓取一个会员信息
     * @param  Integer $id 会员ID
     * @return String|JSON
     */
    public function getMemberInfo($id)
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal&action=getWithdrawalData&id=' . $id,
            [],
            'GET',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 爬取会员信息，一次抓取多个会员信息
     * @param  array  $ids ID数组，不知是不是会员id
     * @return String|JSON
     */
    public function getMembersInfo($ids = [])
    {
        $datas = [];
        foreach ($ids as $id) {
            $datas[$id]['url']  = $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal&action=getWithdrawalData&id=' . $id;
            $datas[$id]['data'] = [
                'delay' => 1000 * 5, // 十秒
            ];
        }
        return $this->request->sendAsync(
            $datas,
            'GET',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 更新出款记录状态
     * @param  Integer $id    记录id
     * @param  Integer $status 操作状态 1 确定、 2 取消、 3 拒绝、 4 锁定、 0 解锁
     * @return String
     */
    public function updateCashWithdrawalStatus($id, $status, $account)
    {
        $date_start = $params['date_start'] ?? date("Y-m-d H:i:s", strtotime("-7 day"));
        $date_end   = $params['date_end'] ?? date("Y-m-d H:i:s", strtotime("+1 hours"));
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=WithdrawalStatus&fun=' . $status . '&wid=' . $id . '&b=1&backurl=/agv3/cl/index.php?module=CashWithdrawal&date_start=' . $date_start . '&date_end=' . $date_end . '&login_name=' . $account,
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
            ]
        );
    }

    /**
     * 修改入款备注内容
     * @param  Integer $id    记录id
     * @param  String $content 备注内容
     * @return String
     */
    public function updateRemark($id, $content)
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal&modift=Y&ID=' . $id . '&CONTENT=' . $content,
            [],
            'GET',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=CashWithdrawal',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 登录页面
     * @return String
     */
    public function loginBefore()
    {
        return $this->request->send(
            $this->bbinUrl . '/user/login',
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl,
            ]
        );
    }

    /**
     * 会员列表，一页一页抓取
     * @param  integer $page 页码
     * @param  string $order 排序
     * @return string
     */
    public function getUsers($page = 1, $order = 'desc')
    {
        return $this->request->send(
            $this->bbinUrl . '/user/list?Status=All&role=All&parent_id=&SearchField=username&SearchValue=&dispensing=all&sort=created_at&order=' . $order . '&Fuzzy=&page=' . $page,
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/user/list?Status=All&role=All&dispensing=all&SearchField=username&SearchValue=',
            ]
        );
    }

    /**
     * 每次爬取 7 页会员数据
     * @param  integer $pages 每次爬取的最后一页
     * @param  string  $order 排序
     * @return string
     */
    public function getUsersEverySevenPages($pages = 7, $order = 'asc')
    {
        $datas = [];
        for ($i = $pages - 6; $i <= $pages; $i++) {
            $datas[$i]['url']  = $this->bbinUrl . '/user/list?Status=All&role=All&parent_id=&SearchField=username&SearchValue=&dispensing=all&sort=created_at&order=' . $order . '&Fuzzy=&page=' . $i;
            $datas[$i]['data'] = [
                'delay' => 1000 * 5, // 十秒
            ];
        }
        return $this->request->sendAsync(
            $datas,
            'GET',
            [
                'Referer' => $this->bbinUrl . '/user/list?Status=All&role=All&dispensing=all&SearchField=username&SearchValue=',
            ]
        );
    }

    /**
     * 充值查询会员
     * @param  string $account 会员账号
     * @return string
     */
    public function queryMember($account)
    {
        /**
         *   {
         *       "flag": "<img src=tpl/common/images/flag/RMB.gif alt=\"flag\" width=\"26\" height=\"15\">",
         *       "Balance_AG": "0.0000",
         *       "Balance_AB": "0.0000",
         *       "Balance_OG": "0.0000",
         *       "Balance_GD": "0.0000",
         *       "Balance_BG": "0.0000",
         *       "Balance_PT": "0.0000",
         *       "Balance_MG": "0.0000",
         *       "Balance_GS": "0.0000",
         *       "Balance_ISB": "0.0000",
         *       "Balance_Sunplus": "0.0000",
         *       "Balance_HB": "0.0000",
         *       "user_id": 289033545,
         *       "user_name": "ceshi1",
         *       "Balance_BB": "550.0000",
         *       "JsAuditValue": 1,
         *       "SpLimit": 10,
         *       "SpRate": 1,
         *       "SpMax": 0,
         *       "AbLimit": 0,
         *       "AbRate": 0,
         *       "AbMax": 0,
         *       "ComplexAudit": "Y",
         *       "ComplexAuditValue": 1,
         *       "NormalityAudit": "Y",
         *       "NormalityAuditValue": 100,
         *       "DailyAbsorbMax_Co": 0,
         *       "AbTotal": 0,
         *       "Currency": "RMB",
         *       "LoginName": "ceshi1",
         *       "HallID": 3820100,
         *       "CHK_ID": "d18688f46705c5ef325f89b4088fee73",
         *       "date": "2017-10-11 04:46:24 PM"
         *   }
         */
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/?module=Deposit&method=query&sid=',
            [
                'form_params' => [
                    'search_name' => $account,
                ],
            ],
            'POST',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=Deposit&method=Source&langx=gb',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 会员充值
     * @param  array  $arr 提交数据
     * @return string
     */
    public function memberDeposit($arr = [])
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/?module=Deposit&method=deposit&sid=',
            [
                'form_params' => [
                    'user_id'             => $arr['user_id'],
                    'hallid'              => $arr['HallID'],
                    'CHK_ID'              => $arr['CHK_ID'],
                    'user_name'           => $arr['user_name'],
                    'date'                => $arr['date'],
                    'Currency'            => 'RMB',
                    'abamount_limit'      => '0',
                    'amount'              => $arr['fee'],
                    'amount_memo'         => $arr['remark'] ?? 'auto_charge',
                    'SpAmount'            => '0',
                    'SpAmount_memo'       => '',
                    'AbAmount'            => '0',
                    'AbAmount_memo'       => '',
                    'ComplexAuditCheck'   => '1',
                    'complex'             => $arr['fee'],
                    'NormalityAuditCheck' => '1',
                    'CommissionCheck'     => 'Y',
                    'DepositItem'         => 'ARD1',
                ],
            ],
            'POST',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/?module=Deposit&method=Source&sort=1',
            ]
        );
    }

    /**
     * 出入库账目汇总
     * @return string
     */
    public function getReport()
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1',
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/user/home',
            ]
        );
    }

    /**
     * 今天或者昨天或者历史的出入款账目汇总
     * @param  array  $arr 参数
     * @return string
     */
    public function getTodayOrYesterdayOrHistoryReport($arr = [])
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1&hallid=' . $arr['hallid'] . '&start=' . $arr['start'] . '&end=' . $arr['end'] . '&currency=CNY&selectdate=' . $arr['selectdate'] . '&timezone=est',
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1',
            ]
        );
    }

    public function getReportL1($arr = [])
    {
        $datas = [];
        foreach ($arr as $val) {
            $url = $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL2';
            $url .= '&pyte=' . $val['pyte'];
            $url .= '&page=' . $val['page'];
            $url .= '&start=' . $val['start'];
            $url .= '&end=' . $val['end'];
            $url .= '&hallid=' . $val['hallid'];
            $url .= '&currency=' . $val['currency'];
            $url .= '&page_num=' . $val['page_num'];
            $url .= '&selectdate=' . $val['selectdate'];
            $url .= '&bb_date=' . $val['bb_date'];
            $url .= '&time_zone=' . $val['time_zone'];
            $url .= '&_=' . Utils::getMillisecond();

            $key                 = $val['pyte'];
            $datas[$key]['url']  = $url;
            $datas[$key]['data'] = [
                'delay' => 1000 * 5, // 十秒
            ];
        }
        return $this->request->sendAsync(
            $datas,
            'GET',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    public function getReportL2($arr = [])
    {
        $datas = [];
        foreach ($arr as $val) {
            $url = $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL3';
            $url .= '&pyte=' . $val['pyte'];
            $url .= '&pyte2=' . $val['pyte2'];
            $url .= '&page=' . $val['page'];
            $url .= '&start=' . $val['start'];
            $url .= '&end=' . $val['end'];
            $url .= '&hallid=' . $val['hallid'];
            $url .= '&currency=' . $val['currency'];
            $url .= '&page_num=' . $val['page_num'];
            $url .= '&selectdate=' . $val['selectdate'];
            $url .= '&bb_date=' . $val['bb_date'];
            $url .= '&time_zone=' . $val['time_zone'];

            $key                 = $val['pyte'] . ',' . $val['pyte2'] . ',' . $val['page'];
            $datas[$key]['url']  = $url;
            $datas[$key]['data'] = [
                'delay' => 1000 * 5, // 十秒
            ];
        }
        return $this->request->sendAsync(
            $datas,
            'GET',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1',
            ]
        );
    }

    /**
     * 会员层级
     * @return String
     */
    public function getLevels()
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=Level',
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/agv3/cl/index.php?module=GeneralLedger2&method=LedgerL1',
            ]
        );
    }

    /**
     * 层级下的会员数据
     * @param  Array  $arr 提交数据
     * @return String
     */
    public function getMembersByLevel($arr = [])
    {
        return $this->request->send(
            $this->bbinUrl . '/agv3/cl/index.php?module=Level&method=levelMemList',
            [
                'form_params' => [
                    'LevelID' => $arr['level_id'],
                    'Page'    => $arr['page'] ?? 1,
                    'Status'  => $arr['status'] ?? 'all', // all、unlocked、locked
                ],
            ],
            'POST',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=Level',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 批量获取层级下的会员信息
     * @param  array  $arr 层级、分页数据
     * @return String
     */
    public function getAsyncMembersByLevel($arr = [])
    {
        $datas = [];
        foreach ($arr as $val) {
            $marker                 = $val['level_id'] . ',' . $val['page'];
            $datas[$marker]['url']  = $this->bbinUrl . '/agv3/cl/index.php?module=Level&method=levelMemList';
            $datas[$marker]['data'] = [
                'delay'       => 1000 * 5, // 十秒
                'form_params' => [
                    'LevelID' => $val['level_id'] ?? '',
                    'Page'    => $val['page'] ?? 1,
                    'Status'  => $val['status'] ?? 'all', // all、unlocked、locked
                ],
            ];
        }
        return $this->request->sendAsync(
            $datas,
            'POST',
            [
                'Referer'          => $this->bbinUrl . '/agv3/cl/index.php?module=Level',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }

    /**
     * 通过会员账号查询会员信息
     * @param  string $account 会员账号
     * @return String
     */
    public function searchMemberInfo($account = '')
    {
        return $this->request->send(
            $this->bbinUrl . '/user/list?Status=All&role=1&dispensing=all&SearchField=username&SearchValue=' . $account,
            [],
            'GET',
            [
                'Referer' => $this->bbinUrl . '/user/list',
            ]
        );
    }

    /**
     * 通过会员id获取会员详细信息
     * @param  integer $uid [description]
     * @return string
     */
    public function getMemberDetails($uid = 0)
    {
        return $this->request->send(
            $this->bbinUrl . '/user/function/json/' . $uid,
            [],
            'GET',
            [
                'Referer'          => $this->bbinUrl . '/user/list',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );
    }
}
