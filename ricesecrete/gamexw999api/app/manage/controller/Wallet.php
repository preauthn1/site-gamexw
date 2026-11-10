<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\GamesModel;
use app\common\model\WalletSystemModel;
use app\common\service\WalletService;
use app\common\Utils;
use app\model\ThirdConfigs;
use think\Request;

class Wallet{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //钱包配置
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $data = $param['info'];
            foreach ($data as $k => $v){
                WalletSystemModel::where('wallet_field', '=', $k)->update(['wallet_value' => $v]);
            }
            $walletSystemModel = new WalletSystemModel();
            WalletService::getWalletData(true);
            return Respons::run(1,'','','',$data,$walletSystemModel,lang('钱包修改'));
        }

        if (isset($param['find'])) {
            //查看当前用户下面设置的站点数据
            $detail = WalletService::getWalletData(true);
            //获取对应平台名称
            $detail['game_type_ids'] = ThirdConfigs::where('tc_id', 'in', $detail['game_type_platform_ids'])->column('tc_name');
            //平台
            $detail['gamesThird']= Utils::getOptions('third_configs',['tc_id', 'tc_name'],['tc_status'=>1]);
            return Respons::run(1, $detail);
        }
    }

}