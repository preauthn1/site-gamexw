<?php
namespace app\manage\controller;
use app\common\model\BankSkModel;
use app\common\model\BankSkRebateSetModel;
use app\common\model\CurrencyModel;
use app\common\model\LangModel;
use app\common\model\UserModel;
use app\common\model\UserLevelModel;
use app\common\model\UserAgentModel;
use app\common\model\BankSkSetModel;
use app\common\lib\Respons;
use app\manage\factory\BankSkFactory;
use think\facade\Session;
use app\common\lib\Util;
use think\facade\View;
use think\Request;
use think\facade\Config;
class BankSk
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new BankSkFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
        if(isset($param['currency'])){
            return Respons::run(1,Util::getCurrency());
        }
    	if (isset($param['add'])) {
            $info = $param['info'];            
            $info['add_time'] = time();
            if(!isset($info['currency_id'])){
                return Respons::run(0,[],lang("货币必须"));
            }
            $currency = CurrencyModel::where('id', $info['currency_id'])->find();
            if (empty($currency)) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], '货币有误');
            }
            if(empty($info['open_start_end_time'])){
                $info['open_start_time'] = 0;
                $info['open_end_time'] = 0;
            }else{
                $t_open_start_end_time=explode(" - ", $info['open_start_end_time']);
//                Util::getTimeZoneConversion(Utils::getQueryDateTime($date[0]), $timezone);
                $info['open_start_time']=strtotime($t_open_start_end_time[0]);
                $info['open_end_time']=strtotime($t_open_start_end_time[1]);
            }
            unset($info['open_start_end_time']);

            $info['recharge_rate']= $currency['recharge_rate'];
            $info['withdrawal_rate']= $currency['withdrawal_rate'];
            unset($info['id'], $info['pay_rebate']);
            $BankSkModel = new BankSkModel();
            $res = $BankSkModel->insertGetId($info);
            if(isset($pay_rebate) && count($pay_rebate) > 0) {
                $BankSkRebateSetModel = (new BankSkRebateSetModel());
                foreach($pay_rebate as $kk=>$vv){
                    $rebate = [];
                    $rebate['min'] = $vv['min'];
                    $rebate['max'] = $vv['max'];
                    $rebate['proportion'] = $vv['proportion'];
                    $rebate['bank_sk_id'] = $res;
                    $rebate['water'] = $vv['water'];
                    $rebate['num_limit'] = $vv['num_limit'];
                    $rebate['day'] = $vv['day'];
                    $rebate['update_time'] = 0;
                    if(isset($vv['id']) && $BankSkRebateSetModel->where(['id' => $vv['id']])->value('id')){
                        $BankSkRebateSetModel->where(['id' => $vv['id']])->update($rebate);
                    }else{
                        $BankSkRebateSetModel->insert($rebate);
                    }
                }
            }
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,$BankSkModel,lang('线下收款账户添加'));

        }
        $type = isset($param['type'])?$param['type']:0;

        $financial_status = 0;
        if($type == 0){ //银行卡的时候 把金融编码展示出来
            $financial = Config::get('custom.financial');
            $defa_lang = LangModel::where(['is_default'=>1])->value('lang');
            if(isset($financial[$defa_lang])){
                $financial_status = 1;
            }
        }
        if($type == 3){
            $pix_type = Config::get('custom.pix_type');
            View::assign("pix_type", $pix_type);
        }
        View::assign("financial_status", $financial_status);
        if($type == 0){
            return View::fetch('bank_sk/add');
        }else if($type == 1){
//            $usdt_type = Util::getUsdtType();
//            View::assign("usdt_type", $usdt_type);
            return View::fetch('bank_sk/add_usdt');
        }else if($type == 2){
            return View::fetch('bank_sk/add_wallet');
        }else if($type == 3){
            return View::fetch('bank_sk/add_pix');
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $currency = CurrencyModel::where('id', $info['currency_id'])->find();
            if (empty($currency)) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], '货币有误');
            }

            if(empty($info['open_start_end_time'])){
                $info['open_start_time'] = 0;
                $info['open_end_time'] = 0;
            }else{
                $t_open_start_end_time=explode(" - ", $info['open_start_end_time']);
                $info['open_start_time']=strtotime($t_open_start_end_time[0]);
                $info['open_end_time']=strtotime($t_open_start_end_time[1]);
            }
            $info['recharge_rate']= $currency['recharge_rate'];
            $info['withdrawal_rate']= $currency['withdrawal_rate'];
            unset($info['open_start_end_time']);
            $BankSkModel=new BankSkModel();
            $data = $BankSkModel->where('id', $info['id'])->find();
            if (empty($data)) {
                return Respons::run(0, [], '信息不存在');
            }
            $rebate = [];
            if(isset($info['pay_rebate']) && count($info['pay_rebate']) > 0) {
                $BankSkRebateSetModel= (new BankSkRebateSetModel());
                foreach($info['pay_rebate']as $kk=>$vv){
                    $rebate = [];
                    $rebate['min'] = $vv['min'];
                    $rebate['max'] = $vv['max'];
                    $rebate['proportion'] = $vv['proportion'];
                    $rebate['water'] = $vv['water'];
                    $rebate['num_limit'] = $vv['num_limit'];
                    $rebate['day'] = $vv['day'];
                    $rebate['bank_sk_id'] = $info['id'];
                    $rebate['update_time'] = 0;
                    if(isset($vv['id']) && $BankSkRebateSetModel->where(['id' => $vv['id']])->value('id')){
                        $BankSkRebateSetModel->where(['id' => $vv['id']])->update($rebate);
                    }else{
                        $BankSkRebateSetModel->insert($rebate);
                    }
                }
            }

            $res=$BankSkModel->update($info);
            $data['open_start_end_time']=date("H:i:s",$data['open_start_time'])." - ".date("H:i:s",$data['open_end_time']);
            return Respons::run($res?1:0,'','',$data,$info,$BankSkModel,lang('线下收款账户修改'));
        }
        if(isset($param['currency'])){
            return Respons::run(1,Util::getCurrency());
        }
        if (isset($param['find'])) {
            $BankSkModel =new BankSkModel();
            $res=$BankSkModel->where("id",$param['id'])->find();
            if($res['open_start_time'] > 0 && $res['open_end_time'] > 0){
                $res['open_start_end_time']=date("H:i:s",$res['open_start_time'])." - ".date("H:i:s",$res['open_end_time']);
                $res['open_start_time_str'] = date("Y-m-d H:i:s",$res['open_start_time']);
                $res['open_end_time_str'] = date("Y-m-d H:i:s",$res['open_end_time']);
            }else{
                $res['open_start_end_time'] = '';
            }
            $res['pay_rebate'] = BankSkRebateSetModel::where(['bank_sk_id' => $res['id']])->select() ?? [];
            return Respons::run(1,$res);
        }
        $type = isset($param['type'])?$param['type']:0;
        View::assign("id",$param['id']);
        $financial_status = 0;
        if($type == 0){ //银行卡的时候 把金融编码展示出来
            $financial = Config::get('custom.financial');
            $defa_lang = LangModel::where(['is_default'=>1])->value('lang');
            if(isset($financial[$defa_lang])){
                $financial_status = 1;
            }
        }
        if($type == 3){
            $pix_type = Config::get('custom.pix_type');
            View::assign("pix_type", $pix_type);
        }
        View::assign("financial_status", $financial_status);
        $vip = UserLevelModel::where("1=1")->column("id,level_name");
        View::assign('vip',$vip);
        if($type == 0){
            return View::fetch('bank_sk/edit');
        }else if($type == 1){
            $usdt_type = Util::getUsdtType();
            View::assign("usdt_type", $usdt_type);
            return View::fetch('bank_sk/edit_usdt');
        }else if($type == 2){
            return View::fetch('bank_sk/edit_wallet');
        }else if($type == 3){
            return View::fetch('bank_sk/edit_pix');
        }
    }


    //编辑
    public function status_edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $BankSkModel=new BankSkModel();
            $data = $BankSkModel->where('id',$info['id'])->field('id,bank_status')->find();
            $res=$BankSkModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$BankSkModel,lang('线下收款账户状态修改'));
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $upd['delete_time']=time();
            $BankSkModel =new BankSkModel();
            $res=$BankSkModel->where("id","in",$id)->update($upd);
            return Respons::run($res?1:0,'','',$param,'',$BankSkModel,lang('线下收款账户删除'));
        }
    }


    public function is_agent($uid){
        return UserAgentModel::where(['uid'=>$uid])->value('id') ?? 0;
    }

    //银行卡参数设置
    public function bank_sk_set(Request $request){

        $param = $request->param();

        if (isset($param['edit'])) {
            header("Content-type: application/json; charset=utf-8");
            $info=$param['info'];
            $info['set_content'] = empty($info['set_content']) ? '':serialize($info['set_content']);
            $BankSkSetModel=new BankSkSetModel();
            $data = $BankSkSetModel->where('pay_type',$info['pay_type'])->find();
            if($data){
                $info['id'] = $data['id'];
                $res=$BankSkSetModel->update($info);
            }else{
                $res=$BankSkSetModel->insert($info);
            }

            return Respons::run($res?1:0,'','',$data,$info,$BankSkSetModel,lang('修改银行卡参数设置'));
        }

        if (isset($param['find'])) {

            $BankSkSetModel =new BankSkSetModel();
            $res=$BankSkSetModel->where("pay_type",$param['pay_type'])->find();

            $ys_data[] = ['title'=>'','remark'=>'','required'=>''];
            if(!$res){
                $res['set_content'] = $ys_data;
            }else{
                $res['set_content'] = empty($res['set_content']) ? $ys_data:unserialize($res['set_content']);
            }

            return Respons::run(1,$res);
        }
    }
}