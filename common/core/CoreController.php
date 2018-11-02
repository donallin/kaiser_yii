<?php
/**
 * User: donallin
 */

namespace common\core;

use common\components\ErrorCode;
use common\components\KsUtils;
use common\services\WexinService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

define('PHOTO_CHECKED', 1);
define('PHOTO_NOT_CHECKED', 2);

class CoreController extends Controller
{
    public $user_id;
    public $user_info;
    public $version;
    public $login_type;
    public $token; // 登录态

    /**
     * @param $action
     * @return bool
     * @throws \yii\web\BadRequestHttpException
     */
    public function beforeAction($action)
    {
        $request = Yii::$app->getRequest();
        $response = Yii::$app->getResponse();
        // 解决js跨域问题
        $http_origin = $request->getOrigin();
        $origin = [
            'http://localhost:9529',
            'http://10.10.20.244:9529'
        ];
        if (preg_match('/([0-9a-zA-Z_-]+.ksgame.com)$/', $http_origin) || preg_match('/([0-9a-zA-Z_-]+.kaiser.com.cn)$/', $http_origin) || in_array($http_origin, $origin)) {
            $response->getHeaders()->set('Access-Control-Allow-Origin', $http_origin);
            $response->getHeaders()->set('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, URI-HASH, X-Token, LOGIN-TYPE');
            $response->getHeaders()->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
            $response->getHeaders()->set('Access-Control-Max-Age', 3600);
        }

        try {
            $this->checkMethod();  // 处理options,delete,put
            $this->checkBrowser(); // 判断浏览器类型
        } catch (\Exception $e) {
            return $this->response($e->getCode(), [], $e->getMessage());
        }
        return parent::beforeAction($action);
    }

    /**
     * @throws \Exception
     */
    private function checkMethod()
    {
        $request = Yii::$app->getRequest();
        $method = strtolower($request->getMethod());
        if (in_array($method, ['options', 'put', 'delete'])) {
            throw new \Exception('options success', ErrorCode::SUCCESS);
        }
    }

    /**
     * @throws \Exception
     */
    private function checkBrowser()
    {
        $request = Yii::$app->getRequest();
        $user_agent = $request->getUserAgent();
        $headers = $request->getHeaders();

        $this->login_type = $login_type = $headers->get('Login-Type');
        if ($login_type == 'wechat' && strpos($user_agent, 'MicroMessenger') === false) { // 微信登录时，必须使用微信浏览器
            throw new \Exception('use wechat', ErrorCode::ERR_WECHAT_BROWSER_CODE);
        }

        if (strpos($user_agent, 'MicroMessenger') === true) { // 微信浏览器设置为微信登录
            $this->login_type = $login_type ? $login_type : 'wechat';
        }
    }

    private function getRedirectUrl($login_type)
    {
        $redirect_url = '';
        if ('wechat' === $login_type) {
            list($bool, $redirect_url) = (new WexinService())->getAuthUrl();
        }

        return $redirect_url;
    }

    /**
     * response
     * @param $code
     * @param array $data
     * @param string $msg
     */
    public function response($code, $data = [], $msg = '')
    {
        $output = [
            'code' => intval($code),
            'msg' => $msg,
            'data' => $data,
            'server_time' => date('Y-m-d H:i:s')
        ];
        $callback = $this->parse('callback', '', 'string');
        if ($callback) {
            $output['callback'] = $callback;
        }
        Yii::$app->response->format = empty($callback) ? Response::FORMAT_JSON : Response::FORMAT_JSONP;
        Yii::$app->response->data = $output;
        return Yii::$app->response->send();
    }


    /**
     * Parse data from request
     * @param string $param
     * @param null $default Default value for this field
     * @param string $type Possibles values of type are: "boolean" or "bool", "integer" or "int", "float" or
     *                         "double", "string", "array", "object", "null"
     * @return mixed
     */
    public function parse($param = '', $default = null, $type = 'integer') // 支持get、post、raw中json传递
    {
        try {
            $request = Yii::$app->getRequest();
            $_request = array_merge($request->getBodyParams(), $request->getQueryParams());

            if (!isset($_request[$param])) {
                KsUtils::setType($default, $type);
                return $default;
            }
            $_request[$param] = trim($_request[$param]);
            KsUtils::setType($_request[$param], $type);
            return $_request[$param];
        } catch (\Exception $e) {
            return $this->response(ErrorCode::ERR_CONFIG_CODE, [], '暂不支持此类型数据'); // 请配置main.php中request的parses
        }
    }

    public function getRequestParams()
    {
        try {
            $request = Yii::$app->getRequest();
            $_request = array_merge($request->getBodyParams(), $request->getQueryParams());
            return $_request;
        } catch (\Exception $e) {
            return $this->response(ErrorCode::ERR_CONFIG_CODE, [], '暂不支持此类型数据');
        }
    }

}