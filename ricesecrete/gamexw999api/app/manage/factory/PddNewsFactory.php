<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\NewsModel;
use app\common\model\LangModel;
use app\common\model\PddNewsModel;
use app\common\Utils;
use think\facade\Request;

class PddNewsFactory
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
        $where=[];
        $NewsModel =new PddNewsModel();
        if (isset($param['news_title']) && $param['news_title']!='') {
            $where[]=["news_title","like","%".$param['news_title']."%"];
        }

        if(isset($param['pdd_id']) && $param['pdd_id'] != ''){
            $where[]=["pdd_id", "=", $param['pdd_id']];
        }

        $rsps['data']=$NewsModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
        }
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}