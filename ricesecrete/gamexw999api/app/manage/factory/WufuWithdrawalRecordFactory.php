<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\WuFuCardsModel;
use app\common\model\WuFuModel;
use app\common\model\WuFuWithdrawalModel;
use think\facade\Request;

class WufuWithdrawalRecordFactory
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
        //获取所有卡片
        $cardArr = WuFuCardsModel::order('id desc')->column('name', 'id');
        $cardArr[-1] = '未中奖';
        $cardArr[0] = '未抽奖';
        //获取所有五福数据
        $wufuArr = WuFuModel::order('id desc')->column('name', 'id');
        $rlt = ReportExport::export(new rptParams('五福兑换记录',
            [
                new rptCol('id', 'ID'),
                'uid:UID',
                'username:用户名',
                new rptCol('w_id','五福名称',rptCol::transType_kvtrans,$wufuArr),
                new rptCol('card_id','卡片名称',rptCol::transType_kvtrans,$cardArr),
                'amount:金额',
                'savings:流水',
                new rptCol('create_time', '时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['savings', 'uid'],//导出表名称使用的字段信息
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
        $wuFuWithdrawalModel =new WuFuWithdrawalModel();
        return $wuFuWithdrawalModel->alias('a')
            ->where($param['where'])->where($param['whereStr'])
            ->field('a.*');
    }
}