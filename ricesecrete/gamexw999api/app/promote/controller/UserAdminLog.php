<?php
namespace app\promote\controller;
use app\BaseController;
use app\common\model\UserAdminLogModel;
use app\common\lib\Respons;
use app\manage\controller\Base;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class UserAdminLog extends Base
{	
    //列表
    public function list(){
        $param = $this->request->param();
        $UserAdminLogModel=new UserAdminLogModel();
        $where=[];
        if (isset($param['uid']) && $param['uid']!='') {
            $where[]=["uid","=",$param['uid']];
        }
        [$data, $total] = $this->getDataList($UserAdminLogModel, $where);
        foreach($data as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
        }
        return $this->success([
            'data' => $data,
            'count' => $total
        ]);
    }

    public function user_list(){
        $param=Request::param();
        View::assign('uid',$param['uid']);
        return View::fetch('user_admin_log/list');
    }

}
