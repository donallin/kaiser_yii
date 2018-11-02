<?php
/**
 * User: donallin
 */

namespace common\components;
class StringUtils
{
    // id={id}&name={name}|id={id}&name={name}
    public static function stringToArray($str, $separator = '|', $sub_separator = '&')
    {
        $r = $str ? explode($separator, $str) : [];
        foreach ($r as $i => $unit) {
            if (strpos($unit, $sub_separator) !== false) {
                $r[$i] = [];
                parse_str($unit, $item);
                foreach ($item as $key => $value) {
                    $r[$i][$key] = $value;
                }
            }
        }
        return $r;
    }
}