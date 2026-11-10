<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\ServiceModel;
use app\common\model\UserModel;
use think\facade\Request;

class ServiceFactory
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
        $ServiceModel =new ServiceModel();
        if (isset($param['title']) && $param['title']!='') {
            $where[]=["name","like","%".$param['title']."%"];
        }
        $rsps['data']=$ServiceModel
            ->where($where)
            ->order("sort desc")
            ->paginate($limit,false,['query' => $param]);
        foreach ($rsps['data'] as &$v){
            $v['agent_id'] = UserModel::where(['id'=>$v['agent_id']])->value('username');
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}