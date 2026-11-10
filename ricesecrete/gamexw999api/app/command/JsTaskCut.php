<?php


namespace app\command;


use app\common\FastGame\Base\JsTaskCut as Js;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class JsTaskCut extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsTaskCut')
            ->setDescription('测试分表任务');
    }

    protected function execute(Input $input, Output $output)
    {
        Js::cuttables();

        //删除dq_user_award_logs表
        $output->warning("开始删除dq_user_award_logs数据");
        //查询五天前完成的流水的记录
        $day = env('clear_award_log_before_day', 10);
        $cfgs = Db::name('user_award_cfgs')->where('uac_waterdone=`uac_water`')
            ->where('uac_is_del', 0)
            ->where('uac_paytime', '<=', time() - 86400 * $day)
            ->field('uac_id,uac_awardid,uac_uid,uac_gameids')
            ->select();
        foreach ($cfgs as $v){
            Db::name('user_award_logs')
                ->where('ual_awardid', '=', $v['uac_awardid'])
                ->where('ual_uid', '=', $v['uac_uid'])
                ->where('ual_gid', 'in', $v['uac_gameids'])
                ->delete();
            Db::name('user_award_cfgs')->where('uac_id', '=', $v['uac_id'])->update(['uac_is_del' => 1]);
        }
    }
}
