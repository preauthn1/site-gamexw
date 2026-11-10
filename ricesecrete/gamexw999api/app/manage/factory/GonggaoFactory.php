<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\GonggaoModel;
use app\common\model\LangModel;
use app\common\Utils;
use think\facade\Request;

class GonggaoFactory
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
        $GonggaoModel =new GonggaoModel();
        if (isset($param['title']) && $param['title']!='') {
            $where[]=["title","like","%".$param['title']."%"];
        }
        $rsps['data']=$GonggaoModel
            ->where($where)
            ->order("sort desc")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$value){
            $value['start_time'] = date("Y-m-d H:i:s",$value['start_time']);
            $value['end_time'] = date("Y-m-d H:i:s",$value['end_time']);
        }
        $langs = LangModel::order('id asc')->select();
        $rsps['langs'] = $langs;
        $rsps['code']=1;
        $rsps['url'] = Utils::getUrlList();
        $rsps['type_list'] = Utils::getOptions('gonggao_type', ['id', 'name'], ['status' => 1], 'id desc');
        return Respons::run($rsps);
    }
}