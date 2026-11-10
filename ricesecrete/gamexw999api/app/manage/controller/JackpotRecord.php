<?php
namespace app\manage\controller;
use app\common\lib\ReportExport;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\JackpotModel;
use app\common\model\JackpotRecordModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\RocketActivityRecordModel;
use app\common\Utils;
use app\manage\factory\JackpotFactory;
use think\Request;
class JackpotRecord
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $jackpotModel =new JackpotModel();
        $condition = [];
        //日期
        if( isset($param['day']) && $param['day'] ){
            $condition[] = ['jr.day', '=', $param['day']];
        }
        //类型
        if( isset($param['j_type']) && $param['j_type'] !== '' ){
            $condition[] = ['jr.type', '=', $param['j_type']];
        }
        //排名
        if( isset($param['ranking']) && $param['ranking'] !== '' ){
            $condition[] = ['jr.ranking', '=', $param['ranking']];
        }
        //用户筛选
        if( isset($param['username']) && $param['username'] ){
            if( is_numeric($param['username']) ){
                $condition[] = ['u.id|u.phone|u.rec_code', '=', $param['username']];
            }else{
                $condition[] = ['u.username|u.mail', 'like', '%'. $param['username']. '%'];
            }
        }
        //最低金额
        if( isset($param['min_amount']) && $param['min_amount'] !== '' ){
            $condition[] = ['jr.price', '>=', $param['min_amount']];
        }
        //最高金额
        if( isset($param['max_amount']) && $param['max_amount'] !== '' ){
            $condition[] = ['jr.price', '<=', $param['max_amount']];
        }

        if( isset($param['export']) && $param['export'] == 1 ){
            return (new JackpotFactory($request->token->uid))->recordExportList($condition);
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'jr.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'jr.id desc';
        }

        $rsps['data']=(new JackpotRecordModel())->alias('jr')
            ->join('user u', 'u.id=jr.uid')
            ->where($condition)
            ->field('jr.*,u.mail,u.phone,u.login_ip_count')
            ->order($order)
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}