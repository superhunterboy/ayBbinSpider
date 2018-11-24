<?php

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Lib\Timer;
use Workerman\Worker;

// 心跳间隔25秒
define('HEARTBEAT_TIME', 55);

// $context = [
//     'ssl' => [
//         'local_cert'  => __DIR__ . '/ssl/yjv5.com_ssl.crt',
//         'local_pk'    => __DIR__ . '/ssl/yjv5.com_ssl.key',
//         'verify_peer' => false,
//     ],
// ];

// $ws_worker            = new Worker("websocket://0.0.0.0:2346", $context);
// $ws_worker->transport = 'ssl';
$ws_worker            = new Worker("websocket://0.0.0.0:2346");
$ws_worker->count     = 1;
$ws_worker->name      = 'WebsocketServer';

$ws_worker->onWorkerStart = function ($worker) use ($ws_worker) {
    // 心跳检测
    Timer::add(10, function () use ($worker) {
        $time_now = time();
        foreach ($worker->connections as $connection) {
            // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
            if (empty($connection->lastMessageTime)) {
                $connection->lastMessageTime = $time_now;
                continue;
            }
            // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
            if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
                $connection->close();
            }
        }
    });
    // 内部服务，爬虫定时任务推送消息用
    $inner_text_worker            = new Worker('text://0.0.0.0:5678');
    $inner_text_worker->onMessage = function ($connection, $buffer) use ($ws_worker) {
        // Websocket 客户端广播消息，要求输入 OTP
        foreach ($ws_worker->connections as $ws_connection) {
            $ws_connection->send($buffer);
        }
        // 内部服务应答
        $connection->send(json_encode(['ret' => 1002, 'msg' => '已通知客户端输入OTP登录']) . "\n");
    };
    $inner_text_worker->listen();
};

$ws_worker->onConnect = function ($connection) {
    $connection->send(json_encode(['ret' => 1004, 'msg' => 'Websocket服务连接成功']));
};

$ws_worker->onMessage = function ($connection, $data) {
    // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
    $connection->lastMessageTime = time();
    // Json 格式：{"otp":"1234567"}
    file_put_contents(__DIR__ . '/data/logs' . date('Ymd') . '.txt', $data . "\n", FILE_APPEND | LOCK_EX);
    $data = json_decode($data, true);
    if (isset($data['otp']) && $data['otp']) {
        if (file_put_contents(__DIR__ . '/data/otp.txt', $data['otp'], LOCK_EX) !== false) {
            echo "The otp.txt file has been created.\n";
        }
    } elseif (isset($data['type']) && $data['type'] && $data['type'] == 'ping') {
        $connection->send(json_encode(['ret' => 9999, 'msg' => 'ok']));
    } else {
        $connection->send(json_encode(['ret' => 1005, 'msg' => 'OTP必须给我，否则我没法登录']));
    }
};

$ws_worker->onClose = function ($connection) use ($ws_worker) {
};

if (!defined('GLOBAL_START')) {
    Worker::$pidFile = __DIR__ . '/data/worker.pid';
    Worker::$logFile = __DIR__ . '/data/worker.log';
    Worker::$stdoutFile = __DIR__ . '/data/stdout.log';
    Worker::runAll();
}
