<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AdminLogModel;
use app\common\Utils;
use think\facade\Request;

class AdminLogFactory
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
        $where = [];
        $handle_name = $param['handle_name'] ??'';
        $admin_name = $param['admin_name'] ??'';
        $date = $param['date'] ??'';
        if ($handle_name) {
            $where[]=["handle_name|forward_data|rear_data","like","%".$param['handle_name']."%"];
        }
        if ($admin_name) {
            $where[]=["admin_name","like","%".$param['admin_name']."%"];
        }
        if ($date) {
            $date=explode(' - ',$param['date']);
            $where[]=["add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        $AdminLogModel =new AdminLogModel();
        $rsps['count']=$AdminLogModel->where($where)->count();
        $rsps['data']=$AdminLogModel->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
        foreach ($rsps['data'] as $key => &$value) {
            $value['add_time']=date("Y-m-d H:i:s",$value['add_time']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}