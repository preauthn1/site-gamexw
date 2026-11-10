<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\BannerModel;
use app\common\model\LangModel;
use app\common\Utils;
use think\facade\Request;

class BannerFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $BannerModel=new BannerModel();
        $where = [];
        if (isset($param['status']) && $param['status'] != '' && in_array($param['status'], [0, 1])) {
            $where[] = ['status', '=', $param['status']];
        }
        if (isset($param['show_position']) && in_array($param['show_position'], [1, 2, 3])) {
            $where[] = ['show_position', '=', $param['show_position']];
        }
        if (isset($param['agent_id']) && $param['agent_id']!='') {
            $where[] = ['agent_id', 'FIND IN SET', $param['agent_id']];
        }

        $rsps['data']=$BannerModel
            ->where($where)
            ->order("sort desc,id desc")->limit($offset, $limit)->select();
        foreach($rsps['data'] as &$val){
            $val['start_time'] = date("Y-m-d H:i:s",$val['start_time']);
            $val['end_time'] = date("Y-m-d H:i:s",$val['end_time']);
        }
        $rsps['code']=1;
        $rsps['url'] = Utils::getUrlList();
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }
}