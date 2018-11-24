<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerUsersTask
{
    /**
     * 一次爬7页用户数据，比如：第一次爬 1,2,3,4,5,6,7 页 第二次爬 2,3,4,5,6,7,8 页
     * 防止在抓取的过程中，新注册的用户没有抓取到
     */
    public static function crawlUsersEverySevenPages()
    {
        $userData = ['lastPage' => '', 'data' => []];
        $currPage = 7;
        $file     = __DIR__ . '/../../data/lastCrawlAsync.txt';
        if (is_file($file)) {
            $tmpData      = json_decode(file_get_contents($file), true);
            $currPage     = $tmpData['currPage'];
            $pageOneUsers = BBINSpider::getInstance()->getUsers(1, 'asc');
            $pageOneUsers = self::parseUserList($pageOneUsers);
            if ($pageOneUsers) {
                $lastPage = $pageOneUsers['lastPage'];
                if ($currPage > $lastPage) {
                    $currPage = $lastPage;
                }
            }
        }
        $userListHtmls = BBINSpider::getInstance()->getUsersEverySevenPages($currPage, 'asc');
        ksort($userListHtmls);
        foreach ($userListHtmls as $page => $userListHtml) {
            $tmpData = self::parseUserList($userListHtml);
            if ($tmpData) {
                $userData['data']     = array_merge($userData['data'], $tmpData['data']);
                $userData['lastPage'] = $tmpData['lastPage'];
            }
        }
        if ($userData && isset($userData['data']) && count($userData['data']) > 0) {
            $users    = $userData['data'];
            $lastPage = $userData['lastPage']; // 最后页码
            $lastUser = end($users); // 最后注册会员ID
            // $userTotal = (count($users) / 7) * $lastPage; // 估计会员总数
            $currPage = $currPage < $lastPage ? $currPage + 1 : $lastPage;
            $tmpData  = [
                'lastUser' => $lastUser[0],
                'lastPage' => $lastPage,
                // 'userTotal' => $userTotal,
                'currPage' => strval($currPage),
            ];
            if (file_put_contents($file, json_encode($tmpData), LOCK_EX) !== false) {
                $config   = require __DIR__ . '/../../config/settings.php';
                $client   = new Client();
                $response = $client->request('POST', $config['api']['add_members'], [
                    'form_params' => [
                        'jsonData' => json_encode($users),
                    ],
                ]);
                if ($response->getStatusCode() == '200') {
                    echo $response->getBody()->getContents();
                }
            }
        }
    }

    /**
     * 一次爬 1 页用户数据，比如：第一次爬 1 页，第二次爬 2 页
     */
    public static function crawlUsers()
    {
        $currPage = 1;
        $file     = __DIR__ . '/../../data/lastCrawl.txt';
        if (is_file($file)) {
            $tmpData      = json_decode(file_get_contents($file), true);
            $currPage     = $tmpData['currPage'];
            $pageOneUsers = BBINSpider::getInstance()->getUsers(1, 'asc');
            $pageOneUsers = self::parseUserList($pageOneUsers);
            if ($pageOneUsers) {
                $lastPage = $pageOneUsers['lastPage'];
                if ($currPage > $lastPage) {
                    $currPage = $lastPage;
                }
            }
        }
        $userListHtml = BBINSpider::getInstance()->getUsers($currPage, 'asc');
        $userData     = self::parseUserList($userListHtml);
        if ($userData && isset($userData['data']) && count($userData['data']) > 0) {
            $users    = $userData['data'];
            $lastPage = $userData['lastPage']; // 最后页码
            $lastUser = end($users); // 最后注册会员ID
            // $userTotal = count($users) * $lastPage; // 估计会员总数
            $currPage = $currPage < $lastPage ? $currPage + 1 : $lastPage;
            $tmpData  = [
                'lastUser' => $lastUser[0],
                'lastPage' => $lastPage,
                // 'userTotal' => $userTotal,
                'currPage' => strval($currPage),
            ];
            if (file_put_contents($file, json_encode($tmpData), LOCK_EX) !== false) {
                $config   = require __DIR__ . '/../../config/settings.php';
                $client   = new Client();
                $response = $client->request('POST', $config['api']['add_members'], [
                    'form_params' => [
                        'jsonData' => json_encode($users),
                    ],
                ]);
                if ($response->getStatusCode() == '200') {
                    echo $response->getBody()->getContents();
                }
            }
        }
    }

    private static function parseUserList($html)
    {
        $tmpArr    = [];
        $tableData = [];
        // $pageTitle = ''; // 页面标识，帐号列表
        $lastPage = ''; // 最后页码
        $crawler  = new Crawler();
        $crawler->addHtmlContent($html);
        try {
            // $pageTitle = $crawler->filterXPath('//div[contains(@class, "container-fluid")]/h3')->text();
            $lastPage = $crawler->filterXPath('//ul[contains(@class, "pagination")]/li')->last()->text();
            $crawler->filterXPath('//table/tbody/tr')->each(
                function (Crawler $tr_node, $tr_i) use (&$tableData) {
                    $uid    = ltrim($tr_node->attr('id'), 'tr_');
                    $ulevel = '';
                    if ($uid > 0) {
                        $tableData[$tr_i][0] = $uid;
                        $ulevel              = self::parseUserLevel(BBINSpider::getInstance()->getMemberDetails($uid));
                    }
                    $tr_node->filterXPath('//td')->each(function (Crawler $td_node, $td_i) use (&$tableData, $ulevel, $tr_i) {
                        $td_i = $td_i + 1;
                        if ($td_i == 4) {
                            if ($ulevel) {
                                $tableData[$tr_i][$td_i] = $ulevel;
                            } else {
                                $tableData[$tr_i][$td_i] = trim(preg_replace("/\\s+/", "", $td_node->text()), ' ');
                            }
                        } elseif ($td_i == 5) {
                            $tableData[$tr_i][$td_i] = $td_node->filterXPath('//img')->attr('title');
                        } elseif ($td_i == 8) {
                            $tableData[$tr_i][$td_i] = $td_node->text();
                        } elseif ($td_i == 9) {
                            $status = [];
                            $td_node->filterXPath('//span[not(contains(@class, "hide"))]')->each(function (Crawler $span_node, $span_i) use (&$status) {
                                array_push($status, $span_node->text());
                            });
                            $tableData[$tr_i][$td_i] = $status;
                        } else {
                            $tableData[$tr_i][$td_i] = trim(preg_replace("/\\s+/", "", $td_node->text()), ' ');
                        }
                    });
                }
            );
        } catch (Exception $e) {
            return false;
            // echo $e->getMessage();
        }
        // $tmpArr['pageTitle'] = $pageTitle;
        $tmpArr['lastPage'] = $lastPage;
        $tmpArr['data']     = $tableData;
        return $tmpArr;
    }

    private static function parseUserLevel($html)
    {
        $jsonStr = json_decode($html, true);
        $result  = $jsonStr['data']['result'] ?? false;
        $data    = $jsonStr['data']['html'] ?? '';
        if ($result) {
            if (preg_match("/层级<\/th>\s*<td>(.*)<\/td>/", $data, $matches)) {
                return $matches[1];
            }
            return null;
        }
        return null;
    }
}
