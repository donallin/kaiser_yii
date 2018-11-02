<?php

namespace common\components;

use yii\base\Component;
use Yii;

class WechatSdk extends Component
{
    public $appId;
    public $appSecret;

    private $accessToken;

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();
        $this->initAccessToken(); // 初始化access_token
    }

    /**
     * @throws \Exception
     */
    private function initAccessToken()
    {
        /**
         * 如果是企业号用以下URL获取access_token
         * $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
         * 如果是微信用以下URL获取access_token
         * $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
         */
        $url = "https://api.weixin.qq.com/cgi-bin/token";

        $redis = KsComponent::redis();

        $tokenKey = RKey::WECHAT_ACCESS_TOKEN;
        $this->accessToken = $redis->get(RKey::WECHAT_ACCESS_TOKEN);
        if (empty($this->accessToken)) {
            $response = HttpUtils::doRequest($url, [
                'grant_type' => 'client_credential',
                'appid' => $this->appId,
                'secret' => $this->appSecret
            ], 'get');
            HttpUtils::checkResponse($response);
            $accessToken = KsUtils::defaultValue('access_token', '', 'string', $response);
            if (!$accessToken) {
                KsUtils::log('access_token empty', 'weixin');
                throw new \Exception('access_token empty', ErrorCode::ERR_EMPTY_CODE);
            }
            $pipe = $redis->multi(\Redis::PIPELINE);
            $pipe->set($tokenKey, $accessToken);
            $pipe->expire($tokenKey, 7000);
            $pipe->exec();

            $this->accessToken = $accessToken;
        }
    }

    /**
     * @return bool|string
     * @throws \Exception
     */
    public function jsApiTicket()
    {
        /**
         * 如果是企业号用以下 URL 获取 ticket
         * $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
         * 如果是微信用以下 URL 获取 ticket
         * $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken"
         */
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket";

        $redis = KsComponent::redis();
        $jsapiTicketKey = RKey::WECHAT_JSAPI_TICKET;
        $ticket = $redis->get($jsapiTicketKey);
        if (empty($ticket)) {
            $response = HttpUtils::doRequest($url, [
                'type' => 'jsapi',
                'access_token' => $this->accessToken
            ], 'get');
            HttpUtils::checkResponse($response);
            $ticket = isset($response['ticket']) ? $response['ticket'] : '';
            if ($ticket) {
                $pipe = $redis->multi(\Redis::PIPELINE);
                $pipe->set($jsapiTicketKey, $ticket);
                $pipe->expire($jsapiTicketKey, 7000);
                $pipe->exec();
            }
        }

        return $ticket;
    }

    /**
     * @param string $auth_code
     * @throws \Exception
     * @return array $oauth
     */
    public function oauth2AccessToken($auth_code = '')
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token";

        $response = HttpUtils::doRequest($url, [
            'appid' => $this->appId,
            'secret' => $this->appSecret,
            'code' => $auth_code,
            'grant_type' => 'authorization_code'
        ], 'get');
        HttpUtils::checkResponse($response);
        $oauth['access_token'] = KsUtils::defaultValue('access_token', '', 'string', $response);
        $oauth['open_id'] = KsUtils::defaultValue('openid', '', 'string', $response);

        return $oauth;
    }

    /**
     * 授权跳转地址
     * @param string $redirect_uri 授权后重定向的回调链接地址， 请使用 urlEncode 对链接进行处理
     * @param string $state 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
     * @return string $url
     */
    public function authUrl($redirect_uri = '', $state = '')
    {
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize';

        $app_id = $this->appId;
        $redirect_uri = urlencode($redirect_uri);
        $state = $state ? $state : '/';

        return "{$url}?appid={$app_id}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
    }

    /**
     * @param $access_token
     * @param $open_id
     * @throws \Exception
     * @return array $user_info
     */
    public function snsUserinfo($access_token, $open_id)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo";

        $response = HttpUtils::doRequest($url, [
            'access_token' => $access_token,
            'openid' => $open_id,
            'lang' => 'zh_CN'
        ], 'get');
        HttpUtils::checkResponse($response);
        $user_info = $response;
        $user_info['province'] = json_encode($response['province']);
        $user_info['open_id'] = KsUtils::defaultValue('openid', '', 'string', $response);
        $user_info['union_id'] = KsUtils::defaultValue('unionid', '', 'string', $response);
        $user_info['head_img_url'] = KsUtils::defaultValue('headimgurl', '', 'string', $response);

        return $user_info;
    }
}