<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\RuleModel;
use app\common\model\LangModel;
use think\facade\Request;

class RuleFactory
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
        $gameId = intval($param['gameId'] ?? 0);
        $type = config('custom.rule_type');
        $where=['game_id' => $gameId];

        if (isset($param['news_title']) && $param['news_title']!='') {
            $where[]=["news_title","like","%".$param['news_title']."%"];
        }
        $rsps['count'] = RuleModel::where($where)->count();
        $rsps['data'] = RuleModel::where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
        foreach($rsps['data'] as &$val){
            $val['type_text'] = lang($type[$val['type']] ?? $val['type']);
        }
        $rsps['code']=1;
        $langs = LangModel::order('id asc')->select();
        $rsps['langs'] = $langs;
        $rsps['type'] = $type;
        return Respons::run($rsps);
    }
}