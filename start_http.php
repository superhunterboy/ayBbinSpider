<?php

require_once __DIR__ . '/vendor/autoload.php';

use Weiming\Libs\Utils;
use Weiming\Spiders\BBINSpider;
use Workerman\Worker;

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
        $orderNo      = $_POST['orderNo'] ?? '';
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
            $member     = json_decode($member, true);
            if (isset($member['LoginName']) && $member['LoginName']) {
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
                if (strpos($result, '所输入的金额数量无法由您的权限做设定') !== false) {
                    $retMsg = '{"ret": 0, "text": "权限不足"}';
                } elseif (strpos($result, '金額輸入不正確') !== false) {
                    $retMsg = '{"ret": 0, "text": "充值金额错误：' . $fee . '"}';
                } elseif (strpos($result, 'alert(') === false) {
                    $retMsg = '{"ret": 1, "text": "充值成功"}';
                } elseif (strpos($result, '防止重复入款') !== false) {
                    $retMsg = '{"ret": 8, "text": "防止重复入款"}';
                }
            } else {
                $retMsg = '{"ret": 0, "text": "用户不存在"}';
            }
        } else {
            $retMsg = '{"ret": -1, "text": "签名验证失败"}';
        }
        file_put_contents(__DIR__ . '/data/logs' . date('Ymd') . '.txt', json_encode($_POST) . '<==>' . $retMsg . '<==>' . $result . "\n", FILE_APPEND | LOCK_EX);
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
    Worker::$pidFile = __DIR__ . '/data/worker.pid';
    Worker::$logFile = __DIR__ . '/data/worker.log';
    Worker::$stdoutFile = __DIR__ . '/data/stdout.log';
    Worker::runAll();
}
