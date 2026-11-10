<?php

namespace app\api\controller;

use app\api\factory\RocketActivityFactory;
use think\Request;

class RocketActivity
{

    /**
     * 加入活动
     * @param Request $request
     * @return \think\response\Json
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function join()
    {
        $rsps = (new RocketActivityFactory())->join_run();
        return json($rsps);
    }

    /**
     *  加注n次燃料
     * @param Request $request
     * @return \think\response\Json
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function inject(Request $request)
    {
        $rsps = (new RocketActivityFactory($request->token->uid))->inject_run();
        return json($rsps);
    }

    /**
     * 获取活动介绍
     * @param Request $request
     * @return \think\response\Json
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function info(Request $request)
    {
        $rsps = (new RocketActivityFactory())->info_run();
        return json($rsps);
    }

    /**
     * 开启宝箱
     * @param Request $request
     * @return \think\response\Json
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function open_box(Request $request)
    {
        $rsps = (new RocketActivityFactory($request->token->uid))->open_box_run();
        return json($rsps);
    }

    /**
     * 获取燃料消耗记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get_fuel_list(Request $request)
    {
        $rsps = (new RocketActivityFactory($request->token->uid))->get_fuel_list_run();
        return json($rsps);
    }

    /**
     * 获取宝箱记录
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get_box_list(Request $request)
    {
        $rsps = (new RocketActivityFactory($request->token->uid))->get_box_list_run();
        return json($rsps);
    }
}