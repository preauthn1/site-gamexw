<?php

namespace app\common\lottery\interfaces;

interface Type {

    public function run();

    /**
     * @param boolean $last 获取上一期单期号
     * @return array|int|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getQishu($last = false);

    /**
     * 开奖
     * @param $qishu
     * @return mixed
     */
    public function draw($qishu = null);

    /**
     * 计算开奖结果
     * @param $qishu
     * @return array
     */
    public function getDrawResult($qishu = null);

    /**
     * 结算注单
     * @param $qishu
     * @return mixed
     */
    public function js($qishu = null);

    public function getResult($result, $group, $type = null);
    public function getResultText($result, $group, $type = null);
}
