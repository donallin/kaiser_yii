<?php
/**
 * User: donallin
 */

namespace common\components;

use yii\base\Component;

class WordsFilter extends Component
{
    public $filePath = '';
    /**
     * trie 字典树
     * @var array
     */
    private $trie = [];

    public function init()
    {
        parent::init();
        self::initWordTrie();
    }

    private function initWordTrie()
    {
        $redis = KsComponent::redis();
        $filter_trie_key = RKey::WORDS_FILTER_TRIE;
        $trie = $redis->get($filter_trie_key);
        if (empty($trie)) {
            $fileStr = file_get_contents($this->filePath);
            $fileArr = explode(',', $fileStr);
            foreach ($fileArr as $v) {
                if (!empty($v)) {
                    self::addWord(trim($v));
                }
            }
            $pipe = $redis->multi(\Redis::PIPELINE); // 如项目无人再使用,便过期
            $pipe->set($filter_trie_key, serialize($this->trie));
            $pipe->expire($filter_trie_key, 14 * 24 * 3600);
            $pipe->exec();
        } else {
            $this->trie = unserialize($trie);
        }
    }

    /**
     * 添加违禁词
     * @param  string|array $word
     * @return bool
     */
    public function addWord($word)
    {
        if (is_array($word)) { // 递归
            array_map([$this, 'addWord'], $word);
            return true;
        }
        $node = &$this->trie;
        $length = mb_strlen($word);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($word, $i, 1);
            if (!isset($node[$char])) {
                $node[$char]['end'] = false;
            }
            if ($i == $length - 1) {
                $node[$char]['end'] = true;
            }
            $node = &$node[$char];
        }
        return true;
    }

    /**
     * 判断字符串是否在 trie 树内
     * @param $text
     * @return bool
     */
    public function isWord($text)
    {
        $node = &$this->trie;
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if (!isset($node[$char])) {
                return false;
            }
            if ($i == ($length - 1) && $node[$char]['end'] === true) {
                return true;
            }
            if ($i == ($length - 1) && $node[$char]['end'] === false) {
                return false;
            }
            $node = &$node[$char];
        }
        return false;
    }

    /**
     * 搜索字符串内的全部违禁词
     * @param $text
     * @return array
     */
    public function search($text)
    {
        $length = mb_strlen($text);
        $node = $this->trie;
        $find = [];
        $position = 0;
        $parent = false;
        $word = '';
        for ($i = 1; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset($node[$char])) {
                $word .= $char;
                $node = $node[$char];
                if ($parent === false) {
                    $position = $i;
                }
                $parent = true;
                if ($node['end']) {
                    $find[] = ['position' => $position, 'word' => $word];
                }
                continue;
            }
            $node = $this->trie;
            $word = '';
            if ($parent) {
                $i = $i - 1;
                $parent = false;
            }

        }
        return $find;
    }

    /**
     * 判断字符串内是否有违禁词
     * @param $text
     * @return bool
     */
    public function check($text)
    {
        $length = mb_strlen($text);
        $node = $this->trie;
        $parent = false;
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset($node[$char])) {
                $node = $node[$char];
                $parent = true;
                if ($node['end']) {
                    return true;
                }
                continue;
            }
            $node = $this->trie;
            if ($parent) {
                $i = $i - 1;
                $parent = false;
            }

        }
        return false;
    }

    /**
     * 替换字符串内的全部违禁词, 有覆盖关系
     * @param $text
     * @return string
     */
    public function replace($text)
    {
        $length = mb_strlen($text);
        $node = $this->trie;
        $position = 0;
        $parent = false;
        $word = '';
        $deep = false;
        $find = [];
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            if (isset($node[$char])) {
                $word .= $char;
                $node = $node[$char];
                if ($parent === false) {
                    $position = $i;
                }
                $parent = true;
                if ($node['end'] && !$deep) {
                    $index = array_search(mb_substr($word, 0, -1), array_column($find, 'word'));
                    if ($index !== false) {
                        unset($find[$index]);
                        $find = array_values($find);
                    }
                    $find[] = ['position' => $position, 'word' => $word];
                }
                continue;
            }
            $node = $this->trie;
            if ($parent) {
                $i = $i - 1;
                $parent = false;
            }
        }
        ksort($find);
        foreach ($find as $item) {
            $text = mb_substr($text, 0, $item['position']) . mb_substr($text, $item['position'] + mb_strlen($item['word']));
        }

        return $text;
    }
}