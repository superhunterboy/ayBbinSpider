<?php

require_once __DIR__ . '/vendor/autoload.php';
// BBIN 后台美东时间
date_default_timezone_set('America/New_York');
//echo date('Y-m-d H:i:s');die;
Weiming\Tasks\TestBBINCrawlerTask::doTask($argv[1]);
