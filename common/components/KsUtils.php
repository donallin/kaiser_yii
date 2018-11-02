<?php
/**
 * User: donallin
 */

namespace common\components;

use Yii;

class KsUtils
{
    // 获取签名
    public static function getSign($params, $appKey)
    {
        if (isset($params['sign'])) {
            unset($params['sign']);
        }
        ksort($params);
        $str = "";
        foreach ($params as $k => $v) {
            $str .= ("$k=" . $v . "&");
        }
        $str .= "key={$appKey}";
        return md5($str);
    }

    public static function getWeek($date)
    {
        $week_arr = ["日", "一", "二", "三", "四", "五", "六"];
        return $week_arr[date('w', strtotime(date('Y-m-d', strtotime($date))))];
    }

    // 获取唯一ID
    public static function getUniqueId($salt, $size = 'big')
    {
        $redis = KsComponent::redis();

        $increase_id_key = RKey::INCREASE_ID;

        $version = 1; // 版本号
        $date_time = date('ymdHis'); // 时间戳
        $sys_id = rand(11, 99); // 机器码
        $increase_id = $redis->get("{$increase_id_key}{$salt}"); // 自增ID
        $redis->incrBy("{$increase_id_key}{$salt}", 1);

        return $size == 'big' ? "{$version}{$date_time}{$increase_id}{$sys_id}" : "{$version}{$increase_id}{$sys_id}";
    }

    // 白名单过滤 支持*号匹配
    public static function validatePath($path, $while_list)
    {
        if (empty($while_list)) {
            return false;
        }
        foreach ($while_list as $unit) {
            if (!preg_match('/\*/', $unit)) { // 没有*号时加结束符
                $unit .= '$';
            }
            $unit = str_replace('/', '\/', $unit);
            $unit = str_replace('*', '', $unit);
            $pattern = "/^{$unit}/";
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    // 格式化，支持单字符或数据
    public static function defaultValue($param, $default = null, $type = 'integer', $array = null, $arr_key = null)
    {
        $data = $arr_key ? (empty($array[$arr_key][$param]) ? null : $array[$arr_key][$param]) : (is_null($array) ? $param : (empty($array[$param]) ? null : $array[$param]));
//        $data = is_null($array) ? $param : (empty($array[$param]) ? null : $array[$param]);
        $unit = null;
        switch ($type) {
            case 'int':
            case 'integer':
                $unit = intval($data);
                $default = !is_null($default) ? $default : 0;
                break;
            case 'float' :
                $unit = round($data, 2);
                $default = !empty($default) ? $default : 0;
                break;
            case 'string' :
                $unit = trim(strval($data));
                $default = !empty($default) ? $default : '';
                break;
            case 'array':
                $unit = (array)$data;
                $default = !empty($default) ? $default : [];
                break;
            case 'percent' :
                $unit = round($data, 4) * 100 . "%";
                $default = !empty($default) ? $default : '0%';
                break;
            default :
                break;
        }
        return is_null($data) ? $default : $unit;
    }

    /**
     * Set the type of a variable
     *
     * @param mixed $var The variable being converted.
     * @param string $type Possibles values of type are: "boolean" or "bool", "integer" or "int", "float" or "double",
     *                     "string", "array", "object", "null"
     */
    public static function setType(&$var, $type = 'string')
    {
        switch ($type) {
            case 'bool':
            case 'boolean':
            case 'int':
            case 'integer':
            case 'float':
            case 'double':
            case 'string':
            case 'array':
            case 'object':
            case 'null':
                settype($var, $type);
                break;
            default:
                break;
        }
    }

    // 获取客户端IP
    public static function getIp()
    {
        //strcasecmp 比较两个字符，不区分大小写。返回0，>0，<0。
        if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches [0] : '';
    }

    //服务器IP
    public static function getServerIp()
    {
        if (isset($_SERVER)) {
            if ($_SERVER['SERVER_ADDR']) {
                $server_ip = $_SERVER['SERVER_ADDR'];
            } else {
                $server_ip = $_SERVER['LOCAL_ADDR'];
            }
        } else {
            $server_ip = getenv('SERVER_ADDR');
        }
        return $server_ip;
    }

    // 获取随机字符串
    public static function mtUrandom($len)
    {
        $urandom = self::_urandom($len);
        return $urandom ? $urandom : self::_mtRand($len);
    }

    // 使用linux随机因子
    private static function _urandom($len)
    {
        $result = '';
        $fp = @fopen('/dev/urandom', 'rb');
        if ($fp !== FALSE) {
            $result .= @fread($fp, 16);
            @fclose($fp);
        } else {
            return $result;
        }
        $result = base64_encode($result);
        $result = strtr($result, '+/', '-_');
        return substr(trim($result, '='), 0, $len);
    }

    // 使用my_rand
    private static function _mtRand($len)
    {
        $asii = "ABCDEFGHJKLMNPQRSTUVXYZ123456789";
        $asii_len = strlen($asii);
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $n = mt_rand(0, $asii_len - 1);
            $str .= $asii{$n};
        }
        return $str;
    }

    //记录本地日志文件
    public static function log($str, $level = 'info', $file = '')
    {
        if (empty($file)) {
            $file = dirname(__DIR__) . "/runtime/logs/" . date('Ymd') . "_{$level}.log";//如果不传，则只是记录一些日常日志
        }
        $path = pathinfo($file);
        if (!file_exists($path['dirname'])) {
            mkdir($path['dirname']);
        }
        $str = "----" . date('Ymd H:i:s') . "----" . $str;
        file_put_contents($file, $str . ",line:" . __LINE__ . "\n", FILE_APPEND);
    }

    /**
     * 导出csv文件
     * @param array $options => [
     *              'file_name' => 'test', // 文件名
     *              'fields' => ['title_1','title_2'], // csv表定义字段
     *              'keys' => ['key_title_1','key_title_2'], // key名称
     *              'rows' => $rows, // 需要加载的数据集合
     *              'file_dir' => '/data/dir' // 需要下载的地址，不传时实时下载 TODO：未实现
     *          ]
     * @return bool
     */
    public static function exportFile($options = [])
    {
        set_time_limit(0); // TODO:如果执行时间超出限期,执行异步
        $now_time = time();
        $date = date('YmdHis', $now_time);
        $file_name = !empty($options['file_name']) ? strval($options['file_name']) : "{$now_time}";
        $file_dir = !empty($options['file_dir']) ? strval($options['file_dir']) : ''; //TODO:保存到file_dir
        $file_head = !empty($options['fields']) ? $options['fields'] : [];
        $keys = !empty($options['keys']) ? $options['keys'] : [];
        $rows = !empty($options['rows']) ? array_values($options['rows']) : [];

        $data_count = count($rows);
        if (empty($data_count)) {
            return false;
        }
        $file_head = array_map(function ($v) {
            return iconv('utf-8', 'gbk', $v);
        }, $file_head);
        $limit = 100000; // 文件行数限制
        $buff_limit = 100000; // buffer缓冲区大小
        $buff_count = 0;
        $tmp_file_arr = [];
        for ($i = 0; $i < ceil($data_count / $limit); $i++) {
            $tmp_file_name = "{$file_name}_{$i}";
            if ($data_count <= $limit) {
                $file_name = $tmp_file_name = "{$file_name}_{$date}";
            }
            $fp = fopen("{$tmp_file_name}.csv", 'w');
            $tmp_file_arr[] = "{$tmp_file_name}.csv";
            fputcsv($fp, $file_head);
            for ($j = 0; $j < $limit; $j++) {
                if (empty($rows[$i * $limit + $j])) {
                    break;
                }
                $buff_count++;
                if ($buff_count >= $buff_limit) {
                    ob_flush();
                    flush();
                    $buff_count = 0;
                }
                $tmp_row = $rows[$i * $limit + $j];
                $arr_row = [];
                foreach ($keys as $unit) {
                    $arr_row[$unit] = isset($tmp_row[$unit]) ? $tmp_row[$unit] : '';
                }
                $arr_row = array_map(function ($v) {
                    return iconv('utf-8', 'gbk', $v);
                }, $arr_row);

                fputcsv($fp, $arr_row);
            }
            fclose($fp);
        }
        if ($data_count <= $limit) { // 生成csv文件
            $csv_file = "{$file_name}.csv";
            header('Content-Description: File Transfer');
            header('Content-Type:vnd.ms-excel;'); //TODO:编码问题
            header('Content-Disposition: attachment;filename="' . $file_name . '.csv"');
            header('Cache-Control: max-age=0,must-revalidate,post-check=0,pre-check=0'); // 不使用缓存,直接访问服务器
            header('Expires:0');
            header('Pragma:public');
            header('Content-Length: ' . filesize($csv_file));
            @readfile($csv_file);
            unlink($csv_file);
        } else { // 生成zip文件
            $zip_file = "{$file_name}_{$date}.zip";
            $zip = new \ZipArchive();
            $zip->open($zip_file, \ZipArchive::CREATE);
            foreach ($tmp_file_arr as $file) {
                $zip->addFile($file, $file);
            }
            $zip->close();

            header("Content-Description: File Transfer");
            header("Content-disposition: attachment; filename={$file_name}_{$date}.zip");//. basename($zip_file));
            header("Content-Type: application/zip");
            header("Content-Transfer-Encoding: binary");
            header("Cache-Control: max-age=0,must-revalidate,post-check=0,pre-check=0"); // TODO:确认cache
            header('Expires:0');
            header('Pragma:public');
            header('Content-Length: ' . filesize($zip_file));
            @readfile($zip_file);
            foreach ($tmp_file_arr as $file) {
                @unlink($file);
            }
            unlink($zip_file);
        }

        return true;
    }

    public static function isJson($str)
    {
        return !is_null(json_decode($str, true));
    }

    public static function isAll($obj)
    {
        $bool = false;
        if (KsUtils::isString($obj)) {
            $bool = $obj == 'all' ? true : false;
        }
        if (KsUtils::isArray($obj)) {
            foreach ($obj as $key => $value) {
                $bool = $value == 'all' && $key === 0 ? true : false;
                if ($bool) {
                    break;
                }
            }
            if (!$bool) {
                $bool = array_key_exists('all', $obj) ? true : false;
            }
        }
        return $bool;
    }

    public static function isString($str)
    {
        return is_string($str);
    }

    public static function isArray($str)
    {
        return is_array($str);
    }

    /**
     * 设置 10 进制转化为 2 进制后某一位的值，并返回对应的 10 进制值
     * @param int|string $int_value 10 进制数字
     * @param int $bit_value 要设置的二进制值：0或者1
     * @param int $bit_index 二进制位置，从低位到高位算起，第一位为 1 ，以此类推
     * @return string|number     返回设置后的10进制值
     */
    public static function setBitToInt($int_value, $bit_value, $bit_index)
    {
        $bit_str = decbin($int_value);

        if ($bit_index < 1) {
            return $int_value;
        }

        // 如果设置指定位为 1
        if ($bit_value == 1) {
            $tmp = 1;
            if ($bit_index > 1) {
                $tmp = $tmp << ($bit_index - 1);
            }

            return ($int_value | $tmp);
        }

        // 如果设置指定位为 0
        $i = 1;
        $tmp = '';
        while ($i <= strlen($bit_str)) {
            if ($i == $bit_index) {
                $tmp = '0' . $tmp;
            } else {
                $tmp = '1' . $tmp;
            }
            $i++;
        }

        $tmp = bindec($tmp);
        return ($int_value & $tmp);
    }

    /**
     * 从10进制数字中获取其二进制中某一位的值
     * @param int $int_value 10进制数字
     * @param int $bit_index 二进制位置，从低位到高位算起，第一位为 1 ，以此类推
     * @return number 返回 0 或者 1
     */
    public static function getBitFromInt($int_value, $bit_index)
    {
        $bit_str = decbin($int_value);
        $bit_len = strlen($bit_str);

        if ($bit_len < $bit_index) {
            return 0;
        }

        $bit = substr($bit_str, $bit_len - $bit_index, 1);
        if ($bit == 0) {
            return 0;
        }

        return 1;
    }

}