<?php


namespace app\common\FastGame\Base;


interface TaskInterface
{
    //public function init($thirdtag);
    public function runThirdTasks($lasttime);
    public function runJs($lasttime);
    public function runFy($lasttime);
    public function runStat($lasttime);
    //public function config($value);
}
