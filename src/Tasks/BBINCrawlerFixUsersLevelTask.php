<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerFixUsersLevelTask
{
    public static function searchUsersLevel()
    {
        $config   = require __DIR__ . '/../../config/settings.php';
        $client   = new Client();
        $response = $client->request('GET', $config['api']['get_not_level_members']);
        if ($response->getStatusCode() == '200') {
            $members = json_decode($response->getBody()->getContents(), true);
            if ($members) {
                $membersLevelArr = [];
                foreach ($members as $member) {
                    $uid          = $member['uid'];
                    $account      = $member['account'];
                    $searchMember = BBINSpider::getInstance()->searchMemberInfo($account);
                    if ($searchMember) {
                        $searchMemberData = self::parseUserList($searchMember);
                        if ($searchMemberData && $searchMemberData[0] == $uid) {
                            $searchMemberLevel = BBINSpider::getInstance()->getMemberDetails($member['uid']);
                            if ($searchMemberLevel) {
                                $membersLevelArr[$uid] = self::parseUserLevel($searchMemberLevel);
                            }
                        }
                    }
                }
                print_r($membersLevelArr);
            }
        }
    }

    private static function parseUserList($html)
    {
        $tableData = [];
        $crawler   = new Crawler();
        $crawler->addHtmlContent($html);
        try {
            $crawler->filterXPath('//table/tbody/tr')->each(
                function (Crawler $tr_node, $tr_i) use (&$tableData) {
                    $tableData[$tr_i][0] = ltrim($tr_node->attr('id'), 'tr_');
                    $tr_node->filterXPath('//td')->each(function (Crawler $td_node, $td_i) use (&$tableData, $tr_i) {
                        $td_i = $td_i + 1;
                        if ($td_i == 5) {
                            $tableData[$tr_i][$td_i] = $td_node->filterXPath('//img')->attr('title');
                        } elseif ($td_i == 8) {
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
        return $tableData;
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
