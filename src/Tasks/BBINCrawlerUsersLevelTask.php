<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerUsersLevelTask
{
    public static function crawlUsersLevel()
    {
        $file = __DIR__ . '/../../data/lastLevels.txt';
        if (is_file($file)) {
            $LevelsData = json_decode(file_get_contents($file), true);
            if (is_array($LevelsData) && count($LevelsData) > 0) {
                $params = [];
                foreach ($LevelsData as $levelId) {
                    $key          = $levelId . ',1';
                    $params[$key] = [
                        'level_id' => $levelId,
                        'page'     => 1,
                    ];
                }
                $onePageDatas = BBINSpider::getInstance()->getAsyncMembersByLevel($params);
                if ($onePageDatas) {
                    $levelPages   = [];
                    $membersLevel = [];
                    // 第一页的数据
                    foreach ($onePageDatas as $key => $value) {
                        $levelAndPage           = explode(',', $key);
                        $levelId                = $levelAndPage[0];
                        $page                   = $levelAndPage[1];
                        $tmpArr                 = json_decode($value, true);
                        $membersLevel[$levelId] = array_merge($tmpArr['locked'], $tmpArr['unlocked']);
                        $totalPage              = $tmpArr['total_page'] ?? 1;
                        if ($totalPage > $page) {
                            $levelPages[$levelId] = range($page + 1, $totalPage);
                        }
                    }
                    // 其他页的数据
                    if ($levelPages) {
                        $params = [];
                        foreach ($levelPages as $levelId => $pages) {
                            foreach ($pages as $page) {
                                $key          = $levelId . ',' . $page;
                                $params[$key] = [
                                    'level_id' => $levelId,
                                    'page'     => $page,
                                ];
                            }
                        }
                        $otherPageDatas = BBINSpider::getInstance()->getAsyncMembersByLevel($params);
                        if ($otherPageDatas) {
                            foreach ($otherPageDatas as $key => $value) {
                                $levelAndPage           = explode(',', $key);
                                $levelId                = $levelAndPage[0];
                                $page                   = $levelAndPage[1];
                                $tmpArr                 = json_decode($value, true);
                                $onPageData             = $membersLevel[$levelId];
                                $membersLevel[$levelId] = array_merge($onPageData, $tmpArr['locked'], $tmpArr['unlocked']);
                            }
                            if ($membersLevel) {
                                $config                        = require __DIR__ . '/../../config/settings.php';
                                $client                        = new Client();
                                $promises['updateMemberLevel'] = $client->requestAsync('POST', $config['api']['update_members_level'], [
                                    'form_params' => [
                                        'jsonData' => json_encode($membersLevel),
                                    ],
                                ]);
                                // 全部完成返回
                                $results = Promise\unwrap($promises);
                                if (isset($results['updateMemberLevel'])) {
                                    $result = $results['updateMemberLevel'];
                                    if ($result->getStatusCode() == 200) {
                                        echo $result->getBody()->getContents();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
