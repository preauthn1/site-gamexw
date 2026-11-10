<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\PddInviteRecordModel;
use think\facade\Request;

class PddInviteRecordFactory
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
        $typeArr = [
            0=>lang('未知'),
            1=>lang('转盘余额'),
            2=>lang('钱包余额')
        ];
        $withdrawalArr = [
            0=>lang('未取款'),
            1=>lang('已取款')
        ];
        $statusArr = [
            0=>lang('未抽奖'),
            1=>lang('已抽奖')
        ];
        $rlt = ReportExport::export(new rptParams('Pdd活动邀请记录',
            [
                new rptCol('id', 'ID'),
                'uid:UID',
                new rptCol('type','类型',rptCol::transType_kvtrans,$typeArr),
                'username:用户username',
                'invite_uid:邀请用户uid',
                'invite_username:邀请用户username',
                'amount:抽奖金额',
                new rptCol('status','状态',rptCol::transType_kvtrans,$statusArr),
                'withdrawal_amount:取款金额',
                new rptCol('is_withdrawal','是否取款',rptCol::transType_kvtrans,$withdrawalArr),
                'turntable_num:转盘次数',
                'finish_turntable_num:已转次数',
                new rptCol('create_time', '时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['type', 'uid'],//导出表名称使用的字段信息
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