<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\UserMsgModel;
use app\common\model\LangModel;
use app\common\model\ActionModel;
use app\common\Utils;
use think\facade\Request;

class UsermsgFactory
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
        $UserMsgModel =new UserMsgModel();
        if (isset($param['title']) && $param['title']!='') {
            $where[]=["title","like","%".$param['title']."%"];
        }
        $rsps['data']=$UserMsgModel->alias("a")
            ->join("dq_user b","b.id=a.uid",'left')
            ->where($where)
            ->where('type', 'in', [3,13])
            ->order("id desc")
            ->field("b.username,a.*")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['read'] = $value['read'] == 1 ? '已读' : '未读';
            $value['username'] = $value['type'] == '13' ? '系统':$value['username'];
        }
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['Action'] = Utils::getOptions('action', ['id', 'dis_code'], [["t_show_start_time", "<=", time()],["t_show_end_time", ">=", time()]]);
        $rsps['tags'] = Utils::getOptions('tag');
        return Respons::run($rsps);
    }
}