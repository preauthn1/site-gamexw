<?php

namespace app\common\model;

use think\Model;

class SLotteryModel extends Model {

    protected $name = 's_lottery';

    //存日志记录字段
    public function getField() {
        $field = [
            'id' => 'id',
            'name' => lang('彩种名称'),
            'icon' => lang('彩种图标'),
            'lottery_type' => lang('彩种类型'),
            'status' => lang('状态'),
            'sort' => lang('排序'),
            'setting' => lang('配置')
        ];
        $set_key = [
            ['status' => [2 => lang('关闭'), 1 => lang('开启')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }


    /**
     * @param $value
     * @return string
     */
    public function setSettingAttr($value) {
        return $value ? json_encode($value) : [];
    }

    /**
     * @param $value
     * @return array|mixed
     */
    public function getSettingAttr($value) {
        return $value ? json_decode($value, true) : [];
    }
}