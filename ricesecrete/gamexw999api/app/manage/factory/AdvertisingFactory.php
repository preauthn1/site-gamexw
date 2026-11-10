<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AdvertisingModel;
use app\common\Utils;
use think\facade\Request;

class AdvertisingFactory
{
    private $uid;//用户id

    /**
     * 条件类型
     * @var string[]
     */
    private $conditionType = [
        1 => '首充',
        2 => '复充'
    ];

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
        $AdvertisingModel = new AdvertisingModel();
        $where = [];
        if (isset($param['title']) && $param['title'] != '') {
            $where[] = ["a.title", "like", "%" . $param['title'] . "%"];
        }
        if (isset($param['agent_id']) && $param['agent_id'] != '') {
            $where[] = ["a.agent_id", "like", "%" . $param['agent_id'] . "%"];
        }
        if (isset($param['status']) && $param['status'] != '') {
            $where[] = ["a.status", "=", $param['status']];
        }
        if (isset($param['lang']) && $param['lang'] != '') {
            $where[] = ["a.lang", "=", $param['lang']];
        }
        $rsps = [];
        $field = 'a.*';
        $rsps['data'] = $AdvertisingModel->alias('a')
            ->where($where)->field($field)
            ->order("a.id desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$value) {
            $value['start_time'] = date("Y-m-d H:i:s", $value['start_time']);
            $value['end_time'] = date("Y-m-d H:i:s", $value['end_time']);
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
            $value['update_time'] = date("Y-m-d H:i:s", $value['update_time']);
            $value['condition_text'] = $this->conditionType[intval($value['condition'])];
        }
        $rsps['code'] = 1;
        $rsps['condition'] = Utils::getOptionsByConfig($this->conditionType);
        $rsps['url'] = Utils::getUrlList();
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }


}