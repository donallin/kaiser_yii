<?php
/**
 * User: donallin
 */

namespace common\components;
class RKey
{
    const EX_ONE_MINUTE = 60;
    const EX_ONE_HOUR = 3600; // 3600
    const EX_ONE_DAY = 86400; // 3600*24
    const EX_ONE_MONTH = 2678400; // 3600*24*30

    const LOGIN_SESSION = 'user:token:'; // 登录态
    const INCREASE_ID = 'system:increase_id:'; // 自增ID self:increase_id:{type}
    const LOCK = 'system:lock:'; // 操作锁 self:lock:{feed_id}{uid} 5秒
    const WECHAT_JSAPI_TICKET = 'system:wechat:jsapi:ticket';
    const WECHAT_ACCESS_TOKEN = 'system:wechat:access_token'; // 普通的access_token
    const WORDS_FILTER_TRIE = 'system:words_filter:trie'; // 词汇筛选字典树

    const SSO_ACCESS_TOKEN = 'system:sso:access_token'; // 普通的access_token
}