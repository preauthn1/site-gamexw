<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Uploads;
use app\common\model\ActionApplyModel;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class ActionApplyFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $ActionApplyModel=new ActionApplyModel();
        $where=[];
        if (isset($param['dis_code']) && $param['dis_code']!='') {
            $where[]=["c.dis_code","like","%".$param['dis_code']."%"];
        }
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["b.id|b.phone","=", $param['username']];
            } else {
                $where[]=["b.username|b.mail|b.phone","=", $param['username']];
            }
        }
        if (isset($param['base_type']) && $param['base_type']!='') {
            $where[]=["c.base_type","=",$param['base_type']];
        }
        if (isset($param['ps_status']) && $param['ps_status']!=='') {
            $where[]=["a.ps_status","=",$param['ps_status']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        if (isset($param['apply_date']) && $param['apply_date']!='') {
            $date=explode(' - ',$param['apply_date']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['export']) && $param['export'] == 1) {

            return $this->exportList($where,'');
//            $rsps['count']=$ActionApplyModel
//                ->alias("a")
//                ->join("dq_user b","a.uid=b.id")
//                ->join("dq_action c","a.action_id=c.id")
//                ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
//                ->where($where)
//                ->count();
//            if ($rsps['count'] > 50000) {
//                $result['code'] = 0;
//                $result['msg'] = '导出数据太大，请增加更多筛选条件';
//                return Respons::run($result);
//            }
//            $lists = $ActionApplyModel
//                ->alias("a")
//                ->join("dq_user b","a.uid=b.id")
//                ->join("dq_action c","a.action_id=c.id")
//                ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
//                ->where($where)
//                ->order("id desc")
//                ->field("a.*,b.username,b.mail,b.phone,b.lebal,c.base_type,c.base_name,c.dis_code,d.ck_number")
//                ->select();
//            $base_type = [1=>lang('注册首存'),2=>lang('再存优惠'),3=>lang('一般优惠'),4=>lang('体验金'),6=>lang('游戏救援金'),7=>lang('推荐奖励')];
//            $ps_text = ['0'=>lang('未派送'),1=>lang('已派送'),3=>lang('已拒绝')];
//            foreach ($lists as &$value) {
//                $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
//                $value['lebal'] = $value['lebal'] == 1 ? '普通用户' : '测试用户';
//                $value['ps_status'] = $ps_text[$value['ps_status']] ?? $value['ps_status'];
//                $value['base_type'] = $base_type[$value['base_type']] ?? $value['base_type'];
//                if($value['ps_status'] == 0){
//                    $value['ps_deta'] = lang('暂无时间');
//                }else{
//                    $value['ps_deta'] = date("Y-m-d H:i:s",$value['ps_deta']);
//                }
//                $value = Utils::hideUserInfo($value);
//            }
        } else {
            $rsps['data']=$ActionApplyModel
                ->alias("a")
                ->join("dq_user b","a.uid=b.id")
                ->join("dq_action c","a.action_id=c.id")
                ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
                ->where($where)
                ->order("id desc")
                ->field("a.*,b.username,b.mail,b.phone,b.lebal,c.base_type,c.base_name,c.dis_code,d.ck_number")
                ->paginate($limit,false,['query' => $param]);
            foreach ($rsps['data'] as &$value) {
                $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
                if($value['ps_status'] == 0){
                    $value['ps_deta'] = lang('暂无时间');
                }else{
                    $value['ps_deta'] = date("Y-m-d H:i:s",$value['ps_deta']);
                }
                $value = Utils::hideUserInfo($value);
            }
        }
//        if (isset($param['export']) && $param['export'] == 1) {
//            $header = [
//                'id' => 'ID',
//                'add_time' => '申请时间',
//                'base_type' => '优惠类型',
//                'dis_code' => '优惠代码',
//                'uid' => 'UID',
//                'mail' => '用户邮箱',
//                'phone' => '手机号',
//                'lebal' => '用户标签',
//                'ck_hl_amount' => '金额',
//                'ps_status' => '派送状态',
//                'ps_deta' => '操作时间',
//            ];
//            $file_name = genCsv($header, $lists,lang('活动申请记录').date('YmdHis',time()), false);
//            $s3_url = Uploads::uploadS3(public_path() . $file_name);
//            Utils::addOptLogByHandle('活动申请记录导出');
//            $result['data'] = $s3_url ?: toMedia($file_name, '/');
//            $result['code']=1;
//            return Respons::run($result);
//        }
        //统计已通过数量，笔数，人数，未通过数量
        $data = [];
        //未审核
        $data['no_check'] = $ActionApplyModel
            ->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
            ->where($where)->where('a.ps_status', '=', 0)->sum(Db::raw('a.ck_hl_amount+a.gd_amount'));
        //派送
        $data['checked'] = $ActionApplyModel
            ->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
            ->where($where)->where('a.ps_status', '=', 1)->sum(Db::raw('a.ck_hl_amount+a.gd_amount'));
        //人数
        $data['people_count'] = $ActionApplyModel
            ->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
            ->where($where)->group('a.uid')->count();
        //已拒绝
        $data['refuse'] = $ActionApplyModel
            ->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
            ->where($where)->where('a.ps_status', '=', 2)->sum(Db::raw('a.ck_hl_amount+a.gd_amount'));

        $rsps['code']=1;
        $rsps['statistics']=$data;
        return Respons::run($rsps);
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
        $baseArr = [
            1=>lang('注册首存'),
            2=>lang('再存优惠'),
            3=>lang('一般优惠'),
            4=>lang('体验金'),
            6=>lang('游戏救援金'),
            7=>lang('推荐奖励'),
        ];
        $psArr = [
            0=>lang('未派送'),
            1=>lang('已派送'),
            2=>lang('已拒绝'),
        ];
        $rlt = ReportExport::export(new rptParams('申请管理列表',
            [
                new rptCol('id', 'ID'),
                new rptCol('add_time', '申请时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('base_type','代理类型',rptCol::transType_kvtrans,$baseArr),
                'dis_code:优惠代码',
                'uid:UID',
                'mail:用户邮箱',
                'phone:手机号',
                new rptCol('lebal','用户标签','app\common\service\ReportDownloadService::getLebal'),
                new rptCol('id','金额','app\manage\factory\ActionApplyFactory::psAmount'),
                new rptCol('ps_status','派送状态',rptCol::transType_kvtrans,$psArr),
                new rptCol('ps_deta', '操作时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['dis_code', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    //派送金额
    public static function psAmount($val, $row){
        return $row['gd_amount'] > 0 ? $row['gd_amount'] : $row['ck_hl_amount'];
    }

    public static function exportListMethod($param)
    {
        $ActionApplyModel=new ActionApplyModel();
        return $ActionApplyModel
            ->alias("a")
            ->where($param['where'])->where($param['whereStr'])
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->leftJoin("dq_user_ck d","a.user_ck_id=d.id")
            ->field("a.*,b.username,b.mail,b.phone,b.lebal,c.base_type,c.base_name,c.dis_code,d.ck_number");
    }
}