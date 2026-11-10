<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\PayAgentModel;
use think\facade\Request;

class PayAgentFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function lists()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $offset = ($page-1)*$limit;
        $where=[];
        $rsps['count'] = PayAgentModel::where($where)->count();
        $rsps['data'] = PayAgentModel::where($where)->limit($offset, $limit)->order("id desc")->select();
        foreach($rsps['data'] as $key => &$val){

        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}