<?php

namespace app\api\factory_v2;

use app\common\model\RuleIndexModel;
use think\facade\Lang;
use think\facade\Request;

//首页规则
class RuleIndexFactory
{
    private $param;//请求参数
    private $lang;//语种

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->lang = strtoupper(Lang::getLangSet());
    }

    //首页规则列表
    public function list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = $this->lang;
        $offset = ($pagenow - 1) * $limit;
        $where = [];
        if (!empty($lang)) {
            $where[] = ["lang", "=", $lang];
        }
        $type = config('custom.rule_index_type');
        $data = RuleIndexModel::where($where)->limit($offset, $limit)->field('id,lang,type,content')->order('sort asc,id asc')->select();
        foreach ($data as $key => &$value) {
            $value['type_text'] = $type[$value['type']] ?? $value['type'];
            $value['content'] = mb_substr(strip_tags($value['content']), 0, 100);
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //首页规则详情
    public function detail_run()
    {
        $type = $this->param['type'] ?? '';
        $id = $this->param['id'] ?? 0;
        if (empty($id) && !is_numeric($type)) {
            ajax_return("参数错误", 0);
        }
        $lang = $this->lang;
        $where = [
            'lang' => $lang
        ];
        if (is_numeric($type)) {
            $where['type'] = $type;
        } else {
            $where['id'] = $id;
        }

        $data = RuleIndexModel::where($where)->field('id,lang,type,content')->find();
        if (empty($data)) {
            ajax_return('数据不存在', 0);
        }
        $type = config('custom.rule_index_type');
        $data['type_text'] = $type[$data['type']] ?? $data['type'];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }
}