<?php
/**
 * User: donallin
 */

namespace common\components;
class ArrayUtils
{
    // Array to Map
    public static function toMap($keys, $arr)
    {
        $r = [];
        foreach ($arr as $i => $row) {
            if (is_string($keys)) {
                $r[$row[$keys]] = $row;
            }
            if (is_array($keys)) {
                $key = [];
                foreach ($keys as $j => $unit) {
                    $key[$j] = !empty($row[$unit]) ? $row[$unit] : '';
                }
                $r[implode('_', $key)] = $row;
            }

        }
        return $r;
    }

    // 将数组的列提出来
    public static function columnToArray($key, $arr)
    {
        return array_unique(array_column($arr, $key));
    }

    /**
     * 1、arrayA和arrayB中同时存在的key，则arrayA和arrayB的值同时写入
     * 2、arrayA和arrayB中不互相存在的key，且直接生成新key传入值
     * @param array $arrayA
     * @param array $arrayB
     * @return array
     */
    public static function arrayMergeRecursive(array $arrayA, array $arrayB)
    {
        $merged = $arrayA;
        foreach ($arrayB as $key => $value) {
            if (is_array($value) && isset ($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::arrayMergeRecursive($merged[$key], $value);
            }
            if (!isset($merged[$key])) { // 新增项
                $merged[$key] = $value;
            }
            if (!is_array($value) || !is_array($merged[$key])) { // 叶子节点
                // 1、数据相同时
                if ($value === $merged[$key]) {
                    $merged[$key] = $value;
                    continue;
                }
                // 2、都是string时
                if (is_string($value) && is_string($merged[$key])) {
                    $merged[$key] = [$value, $merged[$key]];
                    continue;
                }
                // 3、一个为string，一个为array时
                if (gettype($value) !== gettype($merged[$key])) {
                    $value = is_string($value) ? [$value] : $value;
                    $merged[$key] = is_string($merged[$key]) ? [$merged[$key]] : $merged[$key];
                    $merged[$key] = self::arrayMergeRecursive($merged[$key], $value);
                }
            }
        }

        return $merged;
    }
}