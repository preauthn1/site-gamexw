<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use think\facade\Request;

class TaskRecordFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportList($where = null, $whereStr = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.id';
        $params['positionOrder'] = 'DESC';
        $params['muid']=$this->uid;
        $typeArr = TaskModel::getTypeArr();
        $rewardTypeArr = TaskModel::getRewardTypeArr();
        $receiveStatus = TaskRecordModel::getReceiveStatusArr();
        $rlt = ReportExport::export(new rptParams('任务领取记录',
            [
                new rptCol('id', 'ID'),
                'uid:用户id',
                'task_title:任务名称',
                new rptCol('type','类型',rptCol::transType_kvtrans,$typeArr),
                new rptCol('task_condition','任务条件','app\common\service\ReportDownloadService::getTaskCondition'),
                'task_num:达成次数',
                'finish_num:完成数量',
                new rptCol('reward_type','奖励类型',rptCol::transType_kvtrans,$rewardTypeArr),
                'reward_amount_or_integral:奖励数值',
                new rptCol('receive_status','领取状态',rptCol::transType_kvtrans,$receiveStatus),
                new rptCol('create_time', '时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['type', 'uid'],//导出表名称使用的字段信息
            'tr.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $taskRecordModel =new TaskRecordModel();
        return $taskRecordModel->alias('tr')
            ->where($param['where'])->where($param['whereStr'])
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_content tc', "tc.task_id=tr.task_id", 'left')
            ->field('tr.*,u.username as user_username,tc.title as task_title')
            ->order("tr.id desc")
            ->group('tr.id');
    }
}