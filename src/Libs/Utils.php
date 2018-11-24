<?php

namespace Weiming\Libs;

class Utils
{
    /**
     * 发送内部消息
     * @param  array   $msg  消息
     * @param  integer $port 端口
     * @return string
     */
    public static function sendInnerMessage($msg = [], $port = 5678)
    {
        $client = stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errmsg, 1);
        if (!$client) {
            return json_encode(['ret' => $errno, 'msg' => $errmsg]);
        }
        $data = json_encode($msg);
        fwrite($client, $data . "\n");
        $res = fread($client, 8192);
        fclose($client);
        return $res;
    }

    /**
     * 接口验签
     * @param  array  $dataArr 数据
     * @return string
     */
    public static function verifySign($dataArr = [])
    {
        $settings = require __DIR__ . '/../../config/settings.php';
        return md5(md5("orderNo={$dataArr['orderNo']}&account={$dataArr['account']}&fee={$dataArr['fee']}&rechargeTime={$dataArr['rechargeTime']}") . $settings['key']);
    }

    /**
     * 获取毫秒时间戳
     * @return string
     */
    public static function getMillisecond()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float) sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
    }

    /**
     * 提取报表页面数据，以备后用
     * @param  string $html 报表html页面
     * @return array
     */
    public static function parseReportPage($html)
    {
        $tmpArr = [];
        if (preg_match("/\'start\'\s*:\s*\'(\d{2}:\d{2}:\d{2})\',/", $html, $matches)) {
            $tmpArr['start'] = $matches[1];
        }
        if (preg_match("/\'end\'\s*:\s*\'(\d{2}:\d{2}:\d{2})\',/", $html, $matches)) {
            $tmpArr['end'] = $matches[1];
        }
        if (preg_match("/\'hallid\'\s*:\s*\'(\d+)\',/", $html, $matches)) {
            $tmpArr['hallid'] = $matches[1];
        }
        if (preg_match("/\'currency\'\s*:\s*\'(\w+)\',/", $html, $matches)) {
            $tmpArr['currency'] = $matches[1];
        }
        if (preg_match("/\'page_num\'\s*:\s*(\d+),/", $html, $matches)) {
            $tmpArr['page_num'] = $matches[1];
        }
        if (preg_match("/\'selectdate\'\s*:\s*\'(\w+)\',/", $html, $matches)) {
            $tmpArr['selectdate'] = $matches[1];
        }
        if (preg_match("/\'bb_date\'\s*:\s*\'(\d{4}-\d{2}-\d{2})\',/", $html, $matches)) {
            $tmpArr['bb_date'] = $matches[1];
        }
        if (preg_match("/\'time_zone\'\s*:\s*\'(\w+)\'/", $html, $matches)) {
            $tmpArr['time_zone'] = $matches[1];
        }
        return $tmpArr;
    }

    /**
     * 提取报表页面分页数据
     * @param  string $html 报表html页面
     * @return array
     */
    public static function parseReportPageNum($html)
    {
        $pageNum = null;
        if (preg_match("/page_amount\s*=\s*parseInt\('(\d+)',\s*\d+\),/", $html, $matches)) {
            $pageNum = $matches[1];
        }
        return $pageNum;
    }

    /**
     * des-ecb加密
     * @param string  $data 要被加密的数据
     * @param string  $key 加密密钥
     */
    public static function desecbEncrypt($data, $key)
    {
        return openssl_encrypt($data, 'des-ecb', $key);
    }

    /**
     * des-ecb解密
     * @param string  $data 加密数据
     * @param string  $key 加密密钥
     */
    public static function desecbDecrypt($data, $key)
    {
        return openssl_decrypt($data, 'des-ecb', $key);
    }
}
