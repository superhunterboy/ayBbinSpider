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
        'username' => '9fox',
        'password' => 'aa123456',// 'a654321',
    ],
    'key'       => '35a7102186059dr8a1557f1e9c90ca47075d7c4e',
    'api'       => [
        'add_pay_out' => 'http://ay2.com/addPayOut',
        'add_members' => 'http://ay2.com/addMembers',
        'add_reports' => 'http://ay2.com/addReportDatas',
        'add_levels'            => 'http://ay2.com/addLevels',
        'update_members_level'  => 'http://ay2.com/updateMembersLevel',
    ],
];
