<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AgentSettingModel;
use think\facade\Request;

class AgentSettingFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
//        $page=$param['page'] ?? 1;
//        $limit=$param['limit'] ?? 10;
        $where=[];
        $AgentSettingModel =new AgentSettingModel();
        $rsps['count']=$AgentSettingModel->where($where)->count();
//        $rsps['data']=$AgentSettingModel->where($where)->limit((($page-1)*$limit),$limit)->order("id asc")->select();
        $rsps['data']=$AgentSettingModel->where($where)->order("id asc")->select();
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}