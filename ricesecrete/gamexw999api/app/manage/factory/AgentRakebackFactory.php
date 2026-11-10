<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AgentRakeBackModel;
use app\common\model\UserModel;
use app\common\Utils;
use think\facade\Request;

class AgentRakebackFactory
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
        $AgentModel =new AgentRakeBackModel();

        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.create_time",">=", Utils::getQueryDateTime($time[0])];
            $where[]=["a.create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }

        if (isset($param['username']) && $param['username']!='') {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }

        if (isset($param['zd_number']) && $param['zd_number']!='') {
            $where[]=["a.zd_number","=",$param['zd_number']];
        }

        if (isset($param['status']) && $param['status']!='') {
            if (strpos(trim($param['status']),',')) {
                $where[]=["a.status","in",$param['status']];
            }else {
                $where[]=["a.status","=",$param['status']];
            }
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        $rsps['data']=$AgentModel
            ->alias("a")
            ->join('user b', 'b.id=a.uid', 'LEFT')
            ->field('a.*,b.username,b.mail,b.phone')
            ->where($where)
            ->order("a.id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['create_time'] = date("Y-m-d H:i:s",$value['create_time']);
            $value['update_time'] = empty($value['update_time']) ?'':date("Y-m-d H:i:s",$value['update_time']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}