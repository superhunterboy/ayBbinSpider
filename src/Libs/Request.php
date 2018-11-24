<?php

namespace Weiming\Libs;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Promise;
use Exception;

class Request
{
    private $config;

    private $client;

    private $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36',
    ];

    private static $_instance = null;

    private function __construct()
    {
        $this->client = new Client();
        $this->config = require __DIR__ . '/../../config/settings.php';
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (is_null(self::$_instance) || isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function send($url, $data = [], $method = 'GET', $referer = [])
    {
        $options  = $this->getOptions($referer);
        $data     = array_merge($data, $options);
        try{
            $response = $this->client->request($method, $url, $data);
            if ($response->getStatusCode() == '200') {
                return $response->getBody()->getContents();
            }
        }
        catch (Exception $e)
        {
            //http请求失败或超时
            file_put_contents(__DIR__.'/../../data/'.date('Y-m-d').'_FAILED-HTTP-REQUEST.log',date('H:i:s')." url=".$url."\n\n",FILE_APPEND);

        }
        return null;
    }

    public function sendAsync($datas = [], $method = 'GET', $referer = [])
    {
        $returnArr = [];

        $iterable = function () use ($datas, $method, $referer) {
            foreach ($datas as $key => $val) {
                $options = $this->getOptions($referer);
                $url     = $val['url'];
                $data    = isset($val['data']) ? array_merge($val['data'], $options) : $options;
                yield $this->client->requestAsync($method, $url, $data)->then(function ($response) use ($key) {
                    if ($response->getStatusCode() == 200) {
                        return [
                            'key' => $key,
                            'val' => $response->getBody()->getContents(),
                        ];
                    }
                    return null;
                });
            }
        };

        $results = Promise\each_limit(
            $iterable(),
            10,
            function ($result, $index) use (&$returnArr) {
                if ($result) {
                    $returnArr[$result['key']] = $result['val'];
                }
            },
            function ($reason, $index) use (&$returnArr) {
                // var_dump($reason);
            }
        )->wait();

        return $returnArr;
    }

    // public function sendAsync($datas = [], $method = 'GET', $referer = [])
    // {
    //     $returnArr = [];
    //     $promises  = [];
    //     foreach ($datas as $key => $val) {
    //         $options        = $this->getOptions($referer);
    //         $url            = $val['url'];
    //         $data           = isset($val['data']) ? array_merge($val['data'], $options) : $options;
    //         $promises[$key] = $this->client->requestAsync($method, $url, $data);
    //     }
    //     // $results = Promise\unwrap($promises);
    //     $results = Promise\settle($promises)->wait();
    //     foreach ($results as $key => $result) {
    //         $state = $result['state'];
    //         $value = $result['value'] ?? $result['reason'];
    //         if ($state == 'fulfilled' && $value->getStatusCode() == 200) {
    //             $returnArr[$key] = $value->getBody()->getContents();
    //         } else {
    //             print_r($result);
    //         }
    //     }
    //     return $returnArr;
    // }

    private function getOptions($referer = [])
    {
        $options = [
            'headers'         => array_merge($this->headers, $referer),
            'cookies'         => new FileCookieJar(__DIR__ . '/../../data/cookies.txt', true),
            'proxy'           => $this->config['curl']['proxy'],
            'debug'           => $this->config['curl']['debug'],
            'allow_redirects' => false,
            'verify'          => false,
            'timeout'         => 60,
        ];
        if (!$this->config['isProxy']) {
            unset($options['proxy']);
        }
        return $options;
    }
}
