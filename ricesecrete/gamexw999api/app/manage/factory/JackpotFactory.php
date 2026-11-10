<?php

namespace app\manage\factory;


use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\JackpotModel;
use app\common\model\JackpotRecordModel;

class JackpotFactory
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
    public function exportList($where = null)
    {

        $params = \think\facade\Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'j.id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = $this->uid;
        $jTypeArr = [1 => '日榜', 2 => '周榜', 3 => '月榜'];
        $typeArr = [1 => '真实用户', 2 => '假数据'];
        $rlt = ReportExport::export(new rptParams('排行榜数据导出',
            [
                'day:日期',
                'mail:邮箱',
                'phone:号码',
                'username:用户名',
                'ranking:排名',
                new rptCol('j_type','类型',rptCol::transType_kvtrans, $jTypeArr),
                new rptCol('type','数据类型',rptCol::transType_kvtrans, $typeArr),
                'price:金额',
                new rptCol('create_time', '参与时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['day', 'uid', 'j_type', 'type'],//导出表名称使用的字段信息
            'j.ranking', 'asc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $jackpotModel = new JackpotModel();
        return $jackpotModel->alias('j')
            ->join('user u', 'u.id=j.uid', 'left')
            ->where($param['where'])
            ->field('j.*,u.mail,u.phone');
    }

    /**
     * 发放记录导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function recordExportList($where = null)
    {
        $params = \think\facade\Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'jr.id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = $this->uid;
        $jTypeArr = [1 => '日榜', 2 => '周榜', 3 => '月榜'];
        $rlt = ReportExport::export(new rptParams('排行榜数据导出',
            [
                'day:日期',
                'mail:邮箱',
                'phone:号码',
                'ranking:排名',
                new rptCol('type','类型',rptCol::transType_kvtrans, $jTypeArr),
                'price:金额',
                'proportion:比例',
                new rptCol('create_time', '参与时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'recordExportListMethod',
            $params,
            ['day', 'uid', 'type'],//导出表名称使用的字段信息
            'jr.ranking', 'asc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function recordExportListMethod($param)
    {
        $jackpotRecordModel = new JackpotRecordModel();
        return $jackpotRecordModel->alias('jr')
            ->join('user u', 'u.id=jr.uid')
            ->where($param['where'])
            ->field('jr.*,u.mail,u.phone,u.login_ip_count');
    }
}