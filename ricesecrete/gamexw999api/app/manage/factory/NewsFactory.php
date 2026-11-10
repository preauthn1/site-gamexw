<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AgentNewsModel;
use app\common\model\NewsModel;
use app\common\Utils;
use think\facade\Request;

class NewsFactory
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
        $where = [];
        $NewsModel = new NewsModel();
        if (isset($param['title']) && $param['title'] != '') {
            $where[] = ["news_title", "like", "%" . $param['title'] . "%"];
        }

        if (isset($param['type']) && $param['type'] != '') {
            $where[] = ["type", "=", $param['type']];
        }

        if (isset($param['action_id']) && $param['action_id'] != '') {
            $where[] = ["action_id", "=", $param['action_id']];
        }else{
            $where[] = ['action_id', '=', 0];
        }

        $rsps['data'] = $NewsModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$value) {
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
        }
        $rsps['type_list'] = Utils::getOptions('news_type', ['id', 'name'], ['status' => 1], 'id desc');
        $rsps['teach_list'] = Utils::getOptions('teach_type', ['id', 'name'], ['status' => 1], 'id desc');
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function agent_lists()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        $AgentNewsModel = new AgentNewsModel();
        if (isset($param['title']) && $param['title'] != '') {
            $where[] = ["content", "like", "%" . $param['title'] . "%"];
        }
        $where[] = ['pid', '=', 0];
        $rsps['data'] = $AgentNewsModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$value) {
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
        }
        $rsps['type_list'] = Utils::getOptions('news_type', ['id', 'name'], ['status' => 1], 'id desc');
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function agent_daan_lists()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        $AgentNewsModel = new AgentNewsModel();
        if (isset($param['title']) && $param['title'] != '') {
            $where[] = ["b.content|a.rec_code", "like", "%" . $param['title'] . "%"];
        }
        $wh  = [];
        $wh[] = ['pid', '=', 0];
        $agent_new_list = $AgentNewsModel->where($wh)->field('id as value, content as label')->select();
        $where[] = ['a.pid', '>', 0];
        $rsps['data'] = $AgentNewsModel->alias('a')
            ->join('agent_news b', 'a.pid = b.id')
            ->where($where)
            ->field('a.*, b.content as title')
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$value) {
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
        }
        $rsps['type_list'] = Utils::getOptions('news_type', ['id', 'name'], ['status' => 1], 'id desc');
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['agent_new_list'] = $agent_new_list;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}