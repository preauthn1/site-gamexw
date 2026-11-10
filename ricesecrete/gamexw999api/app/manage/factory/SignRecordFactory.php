<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\PddInviteRecordModel;
use think\facade\Request;

class SignRecordFactory
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

        $signStatusArr = [
            0=>lang('补签'),
            1=>lang('正常签到')
        ];
        $statusArr = [
            0=>lang('累计发放'),
            1=>lang('发放当天'),
            2=>lang('发放所有')
        ];
        $rewardArr = [
            0=>lang('未领取'),
            1=>lang('已领取'),
        ];
        $rlt = ReportExport::export(new rptParams('签到记录',
            [
                new rptCol('id', 'ID'),
                'uid:UID',
                'sign_id:签到任务ID',
                'day:签到天数',
                'reward_amount:奖励金额',
                'reward_integral:奖励积分',
                'savings:流水倍数',
                new rptCol('status','状态',rptCol::transType_kvtrans,$statusArr),
                new rptCol('sign_status','签到状态',rptCol::transType_kvtrans,$signStatusArr),
                new rptCol('is_receive_reward','是否领取奖励',rptCol::transType_kvtrans,$rewardArr),
                new rptCol('create_time', '时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['sign_id', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $pddInviteRecordModel =new PddInviteRecordModel();
        return $pddInviteRecordModel->alias('a')
            ->where($param['where'])->where($param['whereStr'])
            ->join('user u', 'u.id=a.invite_uid')
            ->field('a.*');
    }
}