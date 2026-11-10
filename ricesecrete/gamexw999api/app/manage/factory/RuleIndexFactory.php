<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\RuleIndexModel;
use app\common\model\RuleModel;
use app\common\model\LangModel;
use think\facade\Request;

class RuleIndexFactory
{
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
    }

    public function lists()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $type = config('custom.rule_index_type');
        $where = [];
        $rsps['count'] = RuleIndexModel::where($where)->count();
        $rsps['data'] = RuleIndexModel::where($where)->limit((($page - 1) * $limit), $limit)->order("sort asc,id asc")->select();
        foreach ($rsps['data'] as &$val) {
            $val['type_text'] = $type[$val['type']] ?? $val['type'];
        }
        $rsps['code'] = 1;
        $langs = LangModel::order('id asc')->select();
        $rsps['langs'] = $langs;
        $rsps['type'] = $type;
        return Respons::run($rsps);
    }
}