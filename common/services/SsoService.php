<?php
/**
 * User: donallin
 */

namespace common\services;

use common\components\KsComponent;
use common\core\CoreService;

class SsoService extends CoreService
{
    public function getAuthUrl($type = 'jf')
    {
        $ssoSdk = KsComponent::ssoSdk($type);
        return $ssoSdk->authUrl();
    }

    public function getUserInfo($auth_code = '', $type = 'jf')
    {
        try {
            $ssoSdk = KsComponent::ssoSdk($type);
            $user_info = $ssoSdk->loginUserInfo($auth_code);
        } catch (\Exception $e) {
            return [false, ['err_code' => $e->getCode(), 'err_msg' => $e->getMessage()]];
        }
        return [true, $user_info];
    }

    public function getClientUsers($type = 'jf')
    {
        try {
            $ssoSdk = KsComponent::ssoSdk($type);
            $user_list = $ssoSdk->getClientUsers();
        } catch (\Exception $e) {
            return [false, ['err_code' => $e->getCode(), 'err_msg' => $e->getMessage()]];
        }
        return [true, $user_list];
    }
}