<?php

namespace app\api\factory_v2;

use app\common\model\NewsModel;
use think\facade\Lang;
use think\facade\Request;

//常见问题
class NewsFactory
{
    private $param;//请求参数
    private $lang;//语种

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->lang = strtoupper(Lang::getLangSet());
    }

    //问答列表
    public function list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = $this->lang;
        $offset = ($pagenow - 1) * $limit;
        $where = [];
        $where[] = ["type", "=", 0];
        if (!empty($lang)) {
            $where[] = ["lang", "=", $lang];
        }

        $data = NewsModel::where($where)->limit($offset, $limit)->field('id,lang,news_title,news_content')->select();
        foreach ($data as $key => &$value) {
            $value['add_time'] = date("Y-m-d", $value['add_time']);
            $value['news_content'] = mb_substr(strip_tags($value['news_content']), 0, 100);
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //问答详情
    public function detail_run()
    {
        $news_id = $this->param['news_id'] ?? 0;
        if (empty($news_id)) {
            ajax_return("参数错误", 0);
        }
        $data = NewsModel::where("id", $news_id)->field('id,lang,news_title,news_content')->find();
        if (empty($data)) {
            ajax_return('数据不存在', 0);
        }
        $data['add_time'] = date("Y-m-d", $data['add_time']);
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }
}