<?php

namespace app\event;

use app\common\model\SLotteryModel;

class LotteryOpenTimeEvent {

    public $lottery;
    public function __construct(SLotteryModel $lottery) {
        $this->lottery = $lottery;
    }
}