<?php
namespace app\manage\controller;
use app\common\model\UserBalanceMxModel;
use app\common\lib\Respons;
use app\common\service\UserBalanceMxService;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class UserBalanceMx extends Base
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(){
        $param=Request::param();
        $UserBalanceMxModel=new UserBalanceMxModel();
        $where=[];
        if (isset($param['uid']) && $param['uid']!='') {
            $where[]=["uid", "=", $param['uid']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date = explode(',', $param['date']);
            if (isArrayAndNotEmpty($date) && count($date) == 2) {
                $where[] = ["add_time", ">=", Utils::getQueryDateTime($date[0])];
                $where[] = ["add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            }
        }
        if (isset($param['id']) && $param['id']!='') {
            $where[] = ["id", "=", $param['id']];
        }
        if (isset($param['remark']) && $param['remark']!='') {
            $where[] = ["msg", "LIKE", "%{$param['remark']}%"];
        }
        if (isset($param['min_amount']) && $param['min_amount']!=='') {
            $where[] = ["price", ">=", floatval($param['min_amount'])];
        }
        if (isset($param['max_amount']) && $param['max_amount']!=='') {
            $where[] = ["price", "<=", floatval($param['max_amount'])];
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = $param['field'].' '.$param['order'];
        } else {
            $order = 'id desc';
        }
        if (isset($param['type']) && $param['type']!=='') {
            $where[] = ["type", "=", $param['type']];
        }
        //主分类筛选
        $condition1 = null;
        if( isset($param['main_type']) && $param['main_type'] !== '' ){
            $condition1 = UserBalanceMxService::getTypeConditionByKey($param['main_type']);
        }
        //筛选金额大于0的记录
        $where[] = ["price", ">", 0];

        $condition = function($query)use($where,$condition1){
            $query->where($where)->where($condition1);
        };
        [$list, $total] = $this->getDataList($UserBalanceMxModel, $condition, null, $order);
        foreach($list as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['balance_type'] = UserBalanceMxService::getTypeByRemarks($value['msg']);
            $value['balance_type']=lang($value['balance_type']);
            $value['msg'] = empty($value['msg'])?"":lang($value['msg'], [], '', false);
        }
        //类型列表
        $typeWebArr = [];
        foreach (UserBalanceMxService::getTypeList() as $key => $value){
            $typeWebArr[] = ['label' => $value, 'value' => $key];
        }

        return $this->success([
            'data' => $list,
            'total' => $total,
            'type_list' => $typeWebArr,
        ]);
    }

    public function user_list(){
        $param=Request::param();
        View::assign('uid',$param['uid']);
        return View::fetch('user_balance_mx/list');
    }
}
