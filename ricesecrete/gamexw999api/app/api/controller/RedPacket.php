<?php

namespace app\api\controller;

use app\api\factory\RedPacketFactory;
use think\Request;

//红包雨活动
class RedPacket
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
    public function list(Request $request)
    {
        $rsps = (new RedPacketFactory())->list_run();
        return json($rsps);
    }

    public function receive(Request $request){
        $rsps = (new RedPacketFactory($request->token->uid))->receive_run();
        return json($rsps);
    }
}