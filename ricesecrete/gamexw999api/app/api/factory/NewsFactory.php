<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\model\AgentNewsModel;
use app\common\model\NewsModel;
use app\common\model\NewsTypeModel;
use app\common\model\TeachTypeModel;
use app\common\service\MemberService;
use app\manage\controller\TeachType;
use think\facade\Request;

class NewsFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //问答列表
    public function list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $t_id = $this->param['t_id'] ?? 0;
        $offset = ($pagenow - 1) * $limit;
        $where = [];
        $where[] = ["type", "=", 0];
        if (!empty($lang)) {
            $where[] = ["lang", "=", $lang];
        }
        if ($t_id > 0) {
            $where[] = ["type_id", "=", $t_id];
        } else {
            $where[] = ["action_id", "=", 0];
        }
        $data = NewsModel::where($where)->limit($offset, $limit)->select();
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
        $data = NewsModel::where("id", $news_id)->find();
        if (empty($data)) {
            ajax_return('数据不存在', 0);
        }
        $data['add_time'] = date("Y-m-d", $data['add_time']);
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function type_list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $offset = ($pagenow - 1) * $limit;
        $ApiRedis = new ApiRedis();
        $redis_key = md5('type_list_run_v1_' . $lang . '_' . $pagenow . '_' . $limit);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data = NewsTypeModel::where(['status' => 1])->limit($offset, $limit)->select();
            $where = [];
            $where[] = ["type", "=", 0];
            if (!empty($lang)) {
                $where[] = ["lang", "=", $lang];
            }
            foreach ($data as $key => &$value) {
                $wh = [];
                $wh[] = ['type_id', '=', $value['id']];
                $value['name'] = lang($value['name']);
                $value['news_list'] = NewsModel::where($where)->where($wh)->field('add_time, news_title, news_content ')->filter(function ($data) {
                    $data['add_time'] = date("Y-m-d", $data['add_time']);
                })->select();
            }
            $ApiRedis->set($redis_key, $data, 600);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }


    public function teach_list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $offset = ($pagenow - 1) * $limit;
        $ApiRedis = new ApiRedis();
        $redis_key = md5('teach_list_run_v1_' . $lang . '_' . $pagenow . '_' . $limit);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data = TeachTypeModel::where(['status' => 1])->limit($offset, $limit)->select();
            $where = [];
            $where[] = ["type", "=", 2];
            if (!empty($lang)) {
                $where[] = ["lang", "=", $lang];
            }
            foreach ($data as $key => &$value) {
                $wh = [];
                $wh[] = ['type_id', '=', $value['id']];
                $value['name'] = lang($value['name']);
                $value['teach_list'] = NewsModel::where($where)->where($wh)->field('add_time, news_title, news_content')->filter(function ($data) {
                    $data['add_time'] = date("Y-m-d", $data['add_time']);
                })->select();
            }
            $ApiRedis->set($redis_key, $data, 600);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }


    public function agent_list_run()
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ApiRedis = new ApiRedis();
        $t_id = $this->param['t_id'] ?? 0;
        $redis_key = md5('agent_list_run' . $lang . 't_id' . $t_id);
//        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data = AgentNewsModel::where(['pid' => 0])
                ->when($t_id > 0, function ($query) use ($t_id) {
                    $query->where('type_id', $t_id);
                })
                ->filter(function ($data) {
                    $data['son'] = AgentNewsModel::where(['pid' => $data['id']])
                        ->field('rec_code, content')
                        ->filter(function ($d) {
                            $d['rec_link'] = MemberService::createShareUrl($d['rec_code']);
                        })
                        ->select();
                })->field('id, content, icon')
                ->select();
            $ApiRedis->set($redis_key, $data, 600);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }
}