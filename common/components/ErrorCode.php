<?php
/**
 * User: ocean
 * Date: 2016/11/22
 * Time: 16:06
 * Description: 统一定义错误码
 */

namespace common\components;

class ErrorCode
{
    const SUCCESS               = 10000; //成功
    const ERR_PARAM_CODE        = 10001; //参数错误
    const ERR_SYSTEM_CODE       = 10002; //系统繁忙
    const ERR_USER_NOT_LOGIN    = 10003; //用户未登录
    const ERR_CONFIG_CODE       = 10004; //缺少系统配置
    const ERR_EMPTY_CODE        = 10005;//空结果集
    const ERR_OPERATE_FAIL      = 10006;//操作失败
    const ERR_BUSY_CODE         = 10007; //操作过于频繁
    const ERR_HTTP_FAILED       = 10008; // http请求失败
    const ERR_AUTH_FAIL         = 10009; // 缺少权限
    const ERR_SIGN_CODE         = 10010; //签名不合法

    const ERR_WECHAT_BROWSER_CODE   = 20001; // 请使用微信打开
    const ERR_WECHAT_API_FAIL       = 20002; // 微信服务请求非法
    const ERR_API_FAIL              = 20003; // 服务请求非法

    const ERR_DB_CODE           = 90001; //数据库错误
}
