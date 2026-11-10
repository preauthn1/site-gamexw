<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\FalseAccoutModel;
use think\facade\Request;

class FalseAccoutFactory
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
        $where=[];
        $FalseAccoutModel =new FalseAccoutModel();
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["name","like","%".$param['name']."%"];
        }
        $rsps['count']=$FalseAccoutModel->where($where)->count();
        $rsps['data']=$FalseAccoutModel->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}