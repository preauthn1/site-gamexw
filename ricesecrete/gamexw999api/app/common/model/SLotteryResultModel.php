<?php

namespace app\common\model;

use think\Model;

class SLotteryResultModel extends Model {

    protected $name = 's_lottery_result';

    protected $type = [
        'open_time' => 'timestamp'
    ];
}