<?php

require_once __DIR__ . '/vendor/autoload.php';

use Weiming\Libs\Utils;
use Weiming\Spiders\BBINSpider;
use Workerman\Worker;
use GuzzleHttp\Client;

$http_worker        = new Worker("http://0.0.0.0:2345");
$http_worker->name  = 'HttpWorker';
$http_worker->count = 64;

$http_worker->onMessage = function ($connection, $data) {
    $retMsg = 'false';
    if (isset($_POST['act']) && $_POST['act'] == 'updateRemark') {
        $id      = $_POST['id'] ?? 0;
        $content = $_POST['content'] ?? '';
        if ($id > 0 && !empty($content)) {
            $retMsg = BBINSpider::getInstance()->updateRemark($id, $content); // return data: true
        }
    } elseif (isset($_POST['act']) && $_POST['act'] == 'updateStatus') {
        $id      = $_POST['id'] ?? 0;
        $status  = $_POST['status'] ?? -1; // 状态: 1 确定、 2 取消、 3 拒绝、 4 锁定、 0 解锁
        $account = $_POST['account'] ?? '';
        if ($id > 0 && $status >= 0 && !empty($account)) {
            $html = BBINSpider::getInstance()->updateCashWithdrawalStatus($id, $status, $account);
            $html = minify_html(minify_js($html));
            echo "============\n" . $html . "\n============\n";
            if (strpos($html, "ok") !== false) {
                $retMsg = 'true';
            } elseif (strpos($html, "!0") !== false) {
                $retMsg = 'true';
            }
            file_put_contents(__DIR__ . '/data/logs' . date('Ymd') . '.txt', json_encode($_POST) . '<==>' . $retMsg . '<==>' . $html . "\n", FILE_APPEND | LOCK_EX);
        } 
    } elseif (isset($_POST['act']) && $_POST['act'] == 'useRecharge') {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', '6379', '60', 'null', '100');
            $is_lock = $redis->set($_POST['orderNo'], '1', array('nx', 'ex' => 60));
            if (!$is_lock) {
                file_put_contents(__DIR__ . '/data/redisLogs' . date('Ymd') . '.txt', var_export($_POST,true) . "\n", FILE_APPEND | LOCK_EX);
                die('订单加锁失败');
            }
        } catch (Exception $e) {
            file_put_contents(__DIR__ . '/data/exceptionLogs' . date('Ymd') . '.txt', $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }

        $orderNo      = $_POST['orderNo'] ?? '';
        $action       = $_POST['action'] ?? '';
        $account      = $_POST['account'] ?? '';
        $fee          = $_POST['fee'] ?? 0;
        $rechargeTime = $_POST['rechargeTime'] ?? '';
        $remark       = $_POST['remark'] ?? '';
        $sign         = $_POST['sign'] ?? '';
        $verifySign   = Utils::verifySign([
            'orderNo'      => $orderNo,
            'account'      => $account,
            'fee'          => $fee,
            'rechargeTime' => $rechargeTime,
        ]);
        if ($sign == $verifySign) {
            $bbinSpider = BBINSpider::getInstance();
            $member     = $bbinSpider->queryMember($account);
            file_put_contents(__DIR__ . '/data/logs' . date('Ymd') . '.txt',  $member.'<==>'.$orderNo. "\n", FILE_APPEND | LOCK_EX);
            $member     = json_decode($member , true);

            //if (isset($member['LoginName']) && $member['LoginName']) {
                try
                {
                    $result = $bbinSpider->memberDeposit([
                        'user_id'   => $member['user_id'],
                        'HallID'    => $member['HallID'],
                        'CHK_ID'    => $member['CHK_ID'],
                        'user_name' => $member['user_name'],
                        'date'      => $member['date'],
                        'fee'       => $fee,
                        'remark'    => $remark,
                    ]);
                    $result = minify_html(minify_js($result));
                    file_put_contents(__DIR__ . '/data/new-logs' . date('Ymd') . '.txt', json_encode($_POST) . '<==>' . $result ."<===>".print_r($member,true). "\n", FILE_APPEND | LOCK_EX);
                    if (strpos($result, '所输入的金额数量无法由您的权限做设定') !== false) {
                        $retMsg = '{"ret": 0, "text": "权限不足"}';
                    } elseif (strpos($result, '金額輸入不正確') !== false) {
                        $retMsg = '{"ret": 0, "text": "充值金额错误：' . $fee . '"}';
                    } elseif (empty($result) || strpos($result, 'System Error:#0000') !== false) {
                        $retMsg = '{"ret": 0, "text": "充值请求异常"}';
                    } elseif (strpos($result, '系统繁忙，请稍后再试') !== false) {
                        $retMsg = '{"ret": 0, "text": "系统繁忙，请稍后再试"}';
                    } elseif (strpos($result, '防止重复入款') !== false) {
                        $retMsg = '{"ret": 8, "text": "防止重复入款"}';
                    } elseif (strpos($result, 'self.location.href') !== false) {
                        $retMsg = '{"ret": 1, "text": "充值成功"}';
                    } else{
                        $retMsg = '{"ret": 0, "text": "网络请求异常"}';
                    }
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/data/exceptionLogs' . date('Ymd') . '.txt', $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                    $retMsg = '{"ret": 0, "text": "充值请求异常#"}';
                }

            /*
            } else {
                $retMsg = '{"ret": 0, "text": "用户入款查询异常"}';
            }*/
        } else {
            $retMsg = '{"ret": -1, "text": "签名验证失败"}';
        }
        /*
        if($result == ""){
            unlink(__DIR__ . '/data/otp.txt');
            unlink(__DIR__ . '/data/cookies.txt');
        }
        */
        //#通知奥亚财务系统后台##
        $result_bal=json_decode($retMsg,true);
        $param['ret']=$result_bal['ret'];
        $param['text']=$result_bal['text'];
        $param['action']="pay";
        $param['orderNo']=$orderNo;

        $client   = new Client();
        try{
            $response_bak = $client->request('POST', 'http://ay2.com/Sprdercallback', [
                'form_params' => [
                    'jsonData' => json_encode($param),
                ],
            ]);
            if ($response_bak->getStatusCode() == '200') {
                $retMsg_bak = $response_bak->getBody()->getContents();
            }
            else {
                throw new Exception("error");
            }
        }catch (Exception $e){
            sleep(10);
            $response_bak = $client->request('POST', 'http://ay2.com/Sprdercallback', [
                'form_params' => [
                    'jsonData' => json_encode($param),
                ],
            ]);
            if ($response_bak->getStatusCode() == '200') {
                $retMsg_bak = $response_bak->getBody()->getContents();
            }
        }

        //#通知奥亚财务系统后台##

        file_put_contents(__DIR__ . '/data/logs' . date('Ymd') . '.txt', json_encode($_POST) . '<==>' . $retMsg . '<==>' . $result .'<==>'.$retMsg_bak.'<===>'.print_r($param,true). "\n", FILE_APPEND | LOCK_EX);
    } elseif (isset($_POST['act']) && $_POST['act'] == 'pullReportDatas') {
        $date = $_POST['date'] ?? '';
        if ($date && preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            $file = __DIR__ . '/data/crawlReporTime.txt';
            if (file_put_contents($file, json_encode(['date' => $date]), LOCK_EX)) {
                $retMsg = 'true';
            }
        }
    } elseif (isset($_POST['act']) && $_POST['act'] == 'pullHistoryMembers') {
        $currPage = $_POST['currPage'] ?? '';
        if ($currPage && preg_match("/^\d+$/", $currPage)) {
            $file = __DIR__ . '/data/lastCrawlAsync.txt';
            if (is_file($file)) {
                $tmpData             = json_decode(file_get_contents($file), true);
                $lastPage            = $tmpData['lastPage'];
                $tmpData['currPage'] = ($currPage >= 7 && $currPage <= $lastPage ? $currPage : 7);
                if (file_put_contents($file, json_encode($tmpData), LOCK_EX)) {
                    $retMsg = 'true';
                }
            }
        }
    }
    $connection->send($retMsg . "\n");
};

if (!defined('GLOBAL_START')) {
    Worker::$pidFile    = __DIR__ . '/data/worker.pid';
    Worker::$logFile    = __DIR__ . '/data/worker.log';
    Worker::$stdoutFile = __DIR__ . '/data/stdout.log';
    Worker::runAll();
}
