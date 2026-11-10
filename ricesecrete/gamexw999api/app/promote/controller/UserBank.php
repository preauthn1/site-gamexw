<?php
namespace app\promote\controller;
use app\common\model\UserBankModel;
use app\common\lib\Respons;
use think\facade\Session;
use think\facade\View;
use app\common\lib\Util;
use think\facade\Config;
use app\common\model\LangModel;
use think\Request;
use app\manage\factory\UserbankFactory;
class UserBank
{
    protected $middleware = ["Language"];

    //列表接口数据
    public function list(Request $request){
        $rsps = (new UserbankFactory($request->param()['uid']))->list();
        return $rsps;
    }

    //列表接口数据
    public function edit_detail(Request $request){
        $rsps = (new UserbankFactory($request->param()['uid']))->edit_detail();
        return $rsps;
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $res = UserBankModel::where("id","in", $param['id'])->update(['delete_time'=>time()]);
        return Respons::run($res?1:0,'','',$param,'',new UserBankModel(),lang('银行卡删除'));
    }

    //我的银行卡
    public function bank_list(Request $request){
        $channel = Util::getOpenChannel();
        $param = $request->param();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $type = [];
        if(isset($channel[0]) || isset($channel[1])){
            $type[0] = lang('银行卡');
        }
        if(isset($channel[2]) || isset($channel[3])){
            $type[1] = 'USDT';
        }
        if(isset($channel[4])){
            $type[2] = lang('电子钱包');
        }
        if(isset($channel[6])){
            $type[3] = lang('PIX钱包');
        }
        View::assign('uid',$param['uid']);
        View::assign('channel',$channel);
        View::assign('type',$type);
        return View::fetch();
    }

    //我的银行卡
    public function user_bank_list(Request $request){
        $channel = Util::getOpenChannel();
        $param = $request->param();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $type = [];
        if(isset($channel[0]) || isset($channel[1])){
            $type[0] = lang('银行卡');
        }
        if(isset($channel[2]) || isset($channel[3])){
            $type[1] = 'USDT';
        }
        if(isset($channel[4])){
            $type[2] = lang('电子钱包');
        }
        if(isset($channel[6])){
            $type[3] = lang('PIX钱包');
        }
        View::assign('uid',$param['uid']);
        View::assign('channel',$channel);
        View::assign('type',$type);
        return View::fetch('user_bank/bank_list');
    }

    //我的银行卡 公共页面
    public function public_list(Request $request){
        $param = $request->param();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $financial_status = 0;
        if($param['type'] == 0){ //银行卡的时候 把金融编码展示出来
            $financial = Config::get('custom.financial');
            $defa_lang = env('default_country', '');
            if(empty($defa_lang)){
                $defa_lang = LangModel::where(['is_default'=>1])->value('lang');
            }
            if(isset($financial[$defa_lang])){
                $financial_status = 1;
            }
        }
        View::assign('uid',$param['uid']);
        View::assign('financial_status',$financial_status);
        View::assign('type',$param['type']);
        View::assign('is_bank_open', env('is_bank_open', 0));
        return View::fetch();
    }

    public function edit(Request $request){
        $pay_type = ['bank','usdt','wallet','pix'];
        $param = $request->param();
        $info = $param['info'];
        $data = UserBankModel::where('id',$info['id'])->field('id,bank_name,bank_number,bank_open,bank_hm')->find();
        $UserBankModel = new UserBankModel();
        $res = $UserBankModel->update($info);
        return Respons::run($res?1:0,'','',$data,$info,$UserBankModel,lang('银行卡编辑'));
        // $financial_status = 0;
        // if($rsps['data']['bank_type'] == 0){ //银行卡的时候 把金融编码展示出来
        //     $financial = Config::get('custom.financial');
        //     $defa_lang = env('default_country', '');
        //     if(empty($defa_lang)){
        //         $defa_lang = LangModel::where(['is_default'=>1])->value('lang');
        //     }
        //     if(isset($financial[$defa_lang])){
        //         $financial_status = 1;
        //     }
        // }

        // if($param['type'] == 1){
        //     $usdt_type = Util::getUsdtType();
        //     View::assign("usdt_type", $usdt_type);
        // }

        // if($param['type'] == 3){
        //     $pix_type = Config::get('custom.pix_type');
        //     View::assign("pix_type", $pix_type);
        // }


        // View::assign('id',$param['id']);
        // View::assign('financial_status',$financial_status);
        // return View::fetch('user_bank/edit_'.$pay_type[$param['type']]);
    }

}
