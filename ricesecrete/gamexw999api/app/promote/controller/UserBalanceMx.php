<?php
namespace app\promote\controller;
use app\common\model\UserBalanceMxModel;
use app\common\lib\Respons;
use app\manage\controller\Base;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class UserBalanceMx extends Base
{	
    //列表
    public function list(){
        $param=Request::param();
        $UserBalanceMxModel=new UserBalanceMxModel();
        $where=[];
        if (isset($param['uid']) && $param['uid']!='') {
            $where[]=["uid", "=", $param['uid']];
        }
        [$list, $total] = $this->getDataList($UserBalanceMxModel, $where);
        foreach($list as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['msg'] = empty($value['msg'])?"":lang($value['msg']);
        }

        return $this->success([
            'data' => $list,
            'total' => $total,
        ]);
    }

    public function user_list(){
        $param=Request::param();
        View::assign('uid',$param['uid']);
        return View::fetch('user_balance_mx/list');
    }
}
