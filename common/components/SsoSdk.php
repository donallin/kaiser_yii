<?php
/**
 * User: donallin
 */

namespace common\components;

use yii\base\Component;

class SsoSdk extends Component
{
    public $url;
    public $clientId;
    public $clientSecret;
    public $baseUrl;

    private $accessToken;

    /**
     * @throws \Exception
     */
    public function init()
    {
        parent::init();
        $this->initAccessToken();
    }

    /**
     * @throws \Exception
     */
    private function initAccessToken()
    {
        $url = "{$this->url}/api/get-access-token";

        $redis = KsComponent::redis();

        $tokenKey = RKey::SSO_ACCESS_TOKEN;
        $this->accessToken = $redis->get(RKey::SSO_ACCESS_TOKEN);
        if (empty($this->accessToken)) {
            $response = HttpUtils::doRequest($url, [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], 'get');
            HttpUtils::checkResponse($response);
            $accessToken = KsUtils::defaultValue('access_token', '', 'string', $response);
            $expiresIn = KsUtils::defaultValue('expires_in', 0, 'int', $response);
            if (!$accessToken) {
                KsUtils::log('access_token empty', 'sso');
                throw new \Exception('access_token empty', ErrorCode::ERR_EMPTY_CODE);
            }
            $pipe = $redis->multi(\Redis::PIPELINE);
            $pipe->set($tokenKey, $accessToken);
            $pipe->expire($tokenKey, $expiresIn - 1000);
            $pipe->exec();

            $this->accessToken = $accessToken;
        }
    }

    public function authUrl()
    {
        return "{$this->url}/site/login?client_id={$this->clientId}";
    }

    /**
     * @param string $auth_code
     * @return mixed
     * @throws \Exception
     */
    public function loginUserInfo($auth_code = '')
    {
        $url = "{$this->url}/api/login-user-info";

        $response = HttpUtils::doRequest($url, [
            'auth_token' => md5($this->clientSecret . $auth_code)
        ], 'get');
        HttpUtils::checkResponse($response);
        $userInfo = $response['userInfo'];
        return $userInfo;
    }

    /**
     * @return array $user_list
     * @throws \Exception
     */
    public function getClientUsers()
    {
        $url = "{$this->url}/api/get-client-users";

        $response = HttpUtils::doRequest($url, [
            'access_token' => $this->accessToken,
            'client_id' => $this->clientId
        ], 'get');
        HttpUtils::checkResponse($response);
        $userList = $response['data'];
        return $userList;
    }
}