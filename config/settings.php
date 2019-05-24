<?php

return [
    'database'  => [
    ],
    'isProxy'   => false,
    'curl'      => [
        'proxy' => 'socks5://127.0.0.1:1080',
        'debug' => false,
    ],
    'websocket' => [
    ],
    'bbin'      => [
        'domain'   => 'https://js168.9661qw.com',
        //'username' => '9fox',
        //'password' => 'bb123456',
        'username' => 'jishikaifa',   //开发测试用
        'password' => 'aa123456',     //开发测试用
    ],
    'key'       => '35a7102186059dr8a1557f1e9c90ca47075d7c4e',
    'api'       => [
        'add_pay_out' => 'http://ay2.com/addPayOut',
        'add_members' => 'http://ay2.com/addMembers',
        'add_reports' => 'http://ay2.com/addReportDatas',
        'add_levels'            => 'http://ay2.com/addLevels',
        'update_members_level'  => 'http://ay2.com/updateMembersLevel',
        //'add_online_pay' => 'http://157a.com/api/addOnlinePay',
        'add_online_pay' => 'http://www.chart.local/api/addOnlinePay',
    ],
];
