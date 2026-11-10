<?php

namespace app\api\factory;

use app\common\model\AdvertisingModel;
use app\common\service\AgentService;
use think\facade\Request;

class AdvertisingFactory
{
    private $uid;//用户id
    private $param;//请求参数

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
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //广告详情
    public function detail_run()
    {
        $condition = isset($this->param['condition'])?$this->param['condition']:1;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $map = [
            'lang' => $lang,
            'condition' => $condition
        ];
        $time = time();
        $data = AdvertisingModel::alias('a')->where(AgentService::getAgentWhere('agent_id', true, 'a'))->where($map)->where('start_time', '<=', $time)->where('end_time', '>=', $time)->find();
        if (empty($data)) {
            $rsps['data'] = [];
            $rsps['code'] = 0;
        } else {
            $rsps['data'] = $data;
            $rsps['code'] = 1;
        }
        return $rsps;
    }

}