<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\FeedbackModel;
use app\common\model\UserModel;
use app\common\Utils;
use think\facade\Request;

class FeedbackFaction
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $limit=$param['limit'] ?? 10;
        $where = [];

        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }


        $rsps['data'] = FeedbackModel::where($where)->order("id desc")->paginate($limit,false,['query' => $param]);

        foreach ($rsps['data'] as &$value) {
            $value['username'] = UserModel::where("id",$value['uid'])->value('username');
            $value['time'] = empty($value['add_time']) ? '':date("Y-m-d H:i:s",$value['add_time']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}