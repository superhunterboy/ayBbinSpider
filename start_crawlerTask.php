<?php

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Lib\Timer;
use Workerman\Worker;

$task                = new Worker();
$task->count         = 6;
$task->name          = 'CrawlerTask';
$task->onWorkerStart = function ($task) {
    if ($task->id === 0) {
        // OTP登录,出款数据
        Timer::add(15, ['\Weiming\Tasks\BBINCrawlerTask', 'crawlCashWithDrawal']);
    } elseif ($task->id === 1) {
        // 会员数据
        Timer::add(60, ['\Weiming\Tasks\BBINCrawlerUsersTask', 'crawlUsersEverySevenPages']);
    } elseif ($task->id === 2) {
        // 入款报表
        Timer::add(60 * 7, ['\Weiming\Tasks\BBINCrawlerReportTask', 'firstSteps']);
    } elseif ($task->id === 3) {
        // 层级
        Timer::add(60, ['\Weiming\Tasks\BBINCrawlerLevelsTask', 'crawlLevels']);
    } elseif ($task->id === 4) {
        // 会员层级
        Timer::add(60 * 35, ['\Weiming\Tasks\BBINCrawlerUsersLevelTask', 'crawlUsersLevel']);
    } elseif ($task->id === 5) {
        // 在线充值记录
        Timer::add(60 * 5, ['\Weiming\Tasks\BBINCrawlerOnlinePayTask', 'crawlOnlinePay']);
    }
};

if (!defined('GLOBAL_START')) {
    Worker::$pidFile = __DIR__ . '/data/worker.pid';
    Worker::$logFile = __DIR__ . '/data/worker.log';
    Worker::$stdoutFile = __DIR__ . '/data/stdout.log';
    Worker::runAll();
}
