<?php

namespace Weiming\Tasks;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Weiming\Spiders\BBINSpider;

class BBINCrawlerLevelsTask
{
    public static function crawlLevels()
    {
        $pageLevels = BBINSpider::getInstance()->getLevels();
        $levels     = self::parseLevelsList($pageLevels);
        if ($levels) {
            // 层级写入文件
            $file    = __DIR__ . '/../../data/lastLevels.txt';
            $tmpData = [];
            foreach ($levels as $level) {
                // 层级下有会员的数据才要
                if ($level[9] > 0) {
                    array_push($tmpData, $level[1]);
                }
            }
            if (file_put_contents($file, json_encode($tmpData), LOCK_EX) !== false) {
                $config   = require __DIR__ . '/../../config/settings.php';
                $client   = new Client();
                $response = $client->request('POST', $config['api']['add_levels'], [
                    'form_params' => [
                        'jsonData' => json_encode($levels),
                    ],
                ]);
                if ($response->getStatusCode() == '200') {
                    echo $response->getBody()->getContents();
                }
            }
        }
    }

    private static function parseLevelsList($html)
    {
        $tableData = [];
        $crawler   = new Crawler();
        $crawler->addHtmlContent($html);
        try {
            $crawler->filterXPath('//table[@id="level-list"]/tbody[@id="level-list-body"]/tr[@class="level-unit"]')->each(function (Crawler $tr_node, $tr_i) use (&$tableData) {
                $tr_node->filterXPath('//td')->each(function (Crawler $td_node, $td_i) use (&$tableData, $tr_i) {
                    if ($td_i == 3) {
                        $tableData[$tr_i][$td_i] = explode('<br>', $td_node->filterXPath("//input")->attr('value'));
                    }
                    // elseif ($td_i == 9) {
                    //     // 会员数据
                    // }
                    else {
                        $tableData[$tr_i][$td_i] = trim($td_node->text());
                    }
                });
            });
        } catch (Exception $e) {
            return false;
            // echo $e->getMessage();
        }
        return $tableData;
    }
}
