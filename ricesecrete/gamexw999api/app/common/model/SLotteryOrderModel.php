<?php

namespace app\common\model;

use think\Model;

class SLotteryOrderModel extends Model {

    protected $name = 's_lottery_order';

    protected $type = [
        'pay_time' => 'timestamp'
    ];
}