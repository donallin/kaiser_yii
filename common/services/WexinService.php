<?php
/**
 * User: donallin
 */

namespace common\services;

use common\components\KsComponent;
use common\components\KsUtils;
use Yii;

class WexinService
{
    /**
     * jsapi获取配置
     * @param string $referer_url 请求的页面，不包含#之后
     * @return array $signPackage
     */
    public function getJsConfig($referer_url = '')
    {
        try {
            $wechatSdk = KsComponent::wechatSdk();
            // 注意 URL 一定要动态获取，不能 hardcode.
            if (isset($_SERVER['HTTP_REFERER'])) {
                $referer_url = $_SERVER['HTTP_REFERER'];
            }
            if (empty($referer_url)) {
                $referer_url = Yii::$app->params['Jx_Url']; // host地址
            }
            $jsapiTicket = $wechatSdk->jsApiTicket();
            $timestamp = time();
            $nonceStr = KsUtils::mtUrandom(16);

            // 这里参数的顺序要按照 key 值 ASCII 码升序排序
            $string = "jsapi_ticket={$jsapiTicket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$referer_url}";

            $signature = sha1($string);

            $signPackage = array(
                "appId" => $wechatSdk->appId,
                "nonceStr" => KsUtils::defaultValue($nonceStr, '', 'string'),
                "timestamp" => KsUtils::defaultValue($timestamp, '', 'string'),
                "url" => KsUtils::defaultValue($referer_url, '', 'string'),
                "signature" => KsUtils::defaultValue($signature, '', 'string'),
                "rawString" => KsUtils::defaultValue($string, '', 'string')
            );
        } catch (\Exception $e) {
            KsUtils::log('jsapi/sign:' . $e->getCode() . ':' . $e->getMessage(), 'weixin');
            return [false, ['err_code' => $e->getCode(), 'err_msg' => $e->getMessage()]];
        }
        return [true, $signPackage];
    }

    /**
     * 获取用户信息
     * @param string $auth_code
     * @return array $user_info
     */
    public function getUserInfo($auth_code = '')
    {
        try {
            $wechatSdk = KsComponent::wechatSdk();

            $oauth = $wechatSdk->oauth2AccessToken($auth_code);
            $user_info = $wechatSdk->snsUserinfo($oauth['access_token'], $oauth['open_id']);
        } catch (\Exception $e) {
            KsUtils::log('sns/user_info:' . $e->getCode() . ':' . $e->getMessage(), 'weixin');
            return [false, ['err_code' => $e->getCode(), 'err_msg' => $e->getMessage()]];
        }
        return [true, $user_info];
    }

    /**
     * 用户授权跳转地址（获取用户信息）
     * @return array
     */
    public function getAuthUrl()
    {
        try {
            $wechatSdk = KsComponent::wechatSdk();

            $authQy = Yii::$app->params['Wechat_AuthQy'];
            $state = Yii::$app->getRequest()->getHeaders()->get('URI-HASH');
            $url = $wechatSdk->authUrl($authQy['redirect_uri'], $state);
        } catch (\Exception $e) {
            KsUtils::log('oauth2/authorize:' . $e->getCode() . ':' . $e->getMessage(), 'weixin');
            return [false, ['err_code' => $e->getCode(), 'err_msg' => $e->getMessage()]];
        }
        return [true, $url];
    }
}