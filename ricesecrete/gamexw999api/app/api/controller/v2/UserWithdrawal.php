<?php

namespace app\api\controller\v2;

use app\api\factory_v2\UserWithdrawalFactory;
use app\common\lib\Respons;
use think\Request;

class UserWithdrawal
{

    //提现
    public function apply(Request $request)
    {
        //绑定银行卡


        $rsps = (new UserWithdrawalFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }

    //提现限制
    public function withdrawal_config(Request $request)
    {
        $rsps = (new UserWithdrawalFactory($request->token->uid))->withdrawal_config_run();
        return Respons::run($rsps);
    }

    //获取提现渠道
    public function withdrawal_channel(Request $request)
    {
        $rsps = (new UserWithdrawalFactory($request->token->uid))->withdrawal_channel_run();
        return Respons::run($rsps);
    }
}