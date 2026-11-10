<?php

namespace app\common\lottery\type;

use app\common\lottery\interfaces\Type;
use think\Exception;
use think\facade\Db;

/**
 * 彩票基础类
 */
abstract class Base implements Type {
    public $lottery;

    /**
     * 构造方法
     * @access public
     */
    public function __construct($lottery) {
        $this->lottery = $lottery;
        if (empty($this->lottery)) {
            throw new Exception('彩种有误');
        }
    }

    /**
     * 将数字转化为固定位数的字符串
     *
     * @param int $num 待转化的数字
     * @param int $padLength 字符串总长度
     * @param string $padChar 填充字符
     *
     * @return string 返回固定长度的字符串
     */
    public function padNumber(int $num, int $padLength = 4, string $padChar = '0'): string {
        $numLength = strlen((string)$num);
        $padding = max(0, $padLength - $numLength);
        return str_repeat($padChar, $padding) . $num;
    }

    /**
     * 获取N个0-9的随机数
     * @param $num
     * @return array
     */
    public function randBall($num): array {
        $result = [];
        for ($i = 0; $i < $num; $i++) {
            $result[$i] = rand(0,9);
        }
        return $result;
    }
}