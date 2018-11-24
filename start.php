<?php

require_once __DIR__ . '/vendor/autoload.php';

// BBIN 后台美东时间
date_default_timezone_set('America/New_York');

// 标记是全局启动
define('GLOBAL_START', 1);

// 加载IO 和 Web
require_once __DIR__ . '/start_websocket.php';
require_once __DIR__ . '/start_crawlerTask.php';
require_once __DIR__ . '/start_http.php';

Workerman\Worker::$pidFile = __DIR__ . '/data/worker.pid';
Workerman\Worker::$logFile = __DIR__ . '/data/worker.log';
Workerman\Worker::$stdoutFile = __DIR__ . '/data/stdout.log';
// 运行所有服务
Workerman\Worker::runAll();
