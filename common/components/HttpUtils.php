<?php
/**
 * User: donallin
 */

namespace common\components;
class HttpUtils
{
    /**
     * @param string $url
     * @param array $params
     * @param string $method
     * @param array $options
     * @return bool
     */
    const HTTP_POST = 'POST';
    const HTTP_GET = 'GET';

    public static function doRequest($url = '', $params = [], $method = 'POST', $options = [])
    {
        if (!strstr($url, 'http://') && !strstr($url, 'https://')) {
            $url = "http://{$url}";
        }
        $query_string = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $curl = curl_init();
        if (strtoupper($method) == self::HTTP_GET) {
            curl_setopt($curl, CURLOPT_URL, "{$url}?{$query_string}");
        } else {
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query_string);
        }
        if (strpos($url, 'https') === 0) { // HTTPS协议
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        $ret = curl_exec($curl);
        $err_msg = curl_error($curl);
        if (false === $ret || !empty($err_msg)) {
            $result = [
                'code' => false,
                'errcode' => curl_errno($curl),
                'errmsg' => $err_msg,
                'info' => curl_getinfo($curl)
            ];
            curl_close($curl);
            return $result;
        }
        curl_close($curl);
        $result = [
            'code' => true,
            'result' => $ret
        ];
        return $result;
    }

    /**
     * @param array $response
     * @throws \Exception
     */
    public static function checkResponse(&$response = [])
    {
        if (false === $response['code']) {
            throw new \Exception('远程获取数据失败!', ErrorCode::ERR_HTTP_FAILED);
        }
        if (empty($response) || empty($response['result'])) { // HTTP请求失败
            throw new \Exception('解析远程数据失败', ErrorCode::ERR_HTTP_FAILED);
        }

        $response = json_decode($response['result'], true);
        if (!empty($response['code']) && $response['code'] !== ErrorCode::SUCCESS) {
            throw new \Exception("{$response['code']}:{$response['msg']}", ErrorCode::ERR_API_FAIL);
        }
        if (!empty($response['errcode'])) { // 微信错误
            KsUtils::log('error:' . json_encode($response), 'weixin');
            throw new \Exception("{$response['errcode']}:{$response['errmsg']}", ErrorCode::ERR_WECHAT_API_FAIL);
        }
    }
}