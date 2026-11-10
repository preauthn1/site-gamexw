<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\ThirdSetModel;
use think\facade\Request;

class ThirdSetFacctory
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
        $ThirdSetModel =new ThirdSetModel();
        $rsps['count']=$ThirdSetModel
            ->where($where)
            ->count();
        $rsps['data']=$ThirdSetModel
            ->where($where)
            ->limit((($page-1)*$limit),$limit)
            ->order("id desc")
            ->select();
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}