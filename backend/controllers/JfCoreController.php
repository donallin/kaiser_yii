<?php
/**
 * User: donallin
 */

namespace backend\controllers;

use common\components\ErrorCode;
use common\components\KsComponent;
use common\components\KsUtils;
use common\components\RKey;
use common\core\CoreController;
use common\services\SsoService;
use common\services\WexinService;
use Yii;

define('STATUS_ENABLED', 1);
define('STATUS_DISABLED', 2);

class JfCoreController extends CoreController
{
    public $is_super;
    public $white_list = [ // 白名单，不做过滤的路由
        'auth/test/*' => 'auth/test/*',
    ];
    public $sso_type = 'jf';

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        try {
            $this->checkLogin();   // 检测登录态
            // TODO:检测权限
            $this->checkCommonParams(); // 检测公共参数
        } catch (\Exception $e) {
            $errcode = $e->getCode();
            $errmsg = $e->getMessage();
            $redirect_url = $this->getRedirectUrl($this->login_type);
            $data = $errcode == ErrorCode::ERR_USER_NOT_LOGIN ? ['redirect_url' => $redirect_url] : [];
            return $this->response($errcode, $data, $errmsg);
        }
        return true;
    }

    private function checkCommonParams()
    {
        // 1、version
        $this->version = $this->parse('version', '', 'string');
    }

    /**
     * check login
     * @throws \Exception
     */
    private function checkLogin()
    {
        $redis = KsComponent::redis();
        $request = Yii::$app->getRequest();
        $headers = $request->getHeaders();

        $login_key = RKey::JF_LOGIN_SESSION; // 登录态

        // TOKEN验证
        $token = $headers->get('X-Token'); //token有效时间
        $expire_time = 3 * 24 * 3600;
        $user_info = $redis->hGetAll("{$login_key}{$token}");
        $path = strtolower($request->getPathInfo());
        if (KsUtils::validatePath($path, $this->white_list) && empty($user_info)) { // 白名单；如果用户信息正确,不走白名单
            return true;
        }
        if (empty($token)) {
            throw new \Exception('param token no exists', ErrorCode::ERR_USER_NOT_LOGIN);
        }
        if (empty($user_info)) {
            throw new \Exception('user not login', ErrorCode::ERR_USER_NOT_LOGIN);
        }
        $redis->expire("{$login_key}{$token}", $expire_time); // 每次取，设置有效时间
        $this->user_id = $user_info['user_id'];
        $this->user_info = $user_info;
        $this->token = $token;
    }

    private function getRedirectUrl($login_type)
    {
        $redirect_url = '';
        if ('kaiser' === $login_type) {
            $redirect_url = (new SsoService())->getAuthUrl($this->sso_type);
        }
        if ('wechat' === $login_type) {
            list($bool, $redirect_url) = (new WexinService())->getAuthUrl();
        }

        return $redirect_url;
    }
}