<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\TelegrambotConfigModel;
use app\common\model\TelegrambotMessageModel;
use app\api\factory\BotFactory;
use app\common\Utils;
use think\Request;
use think\facade\Session;
use think\facade\Db;


class TelegramBot
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //消息列表
    public function index(Request $request){
        $param = $request->param();
        $page = $param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $TelegrambotModel = new TelegrambotMessageModel();
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['data']=$TelegrambotModel->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //配置
    public function config(Request $request){
        $param = $request->param();
        $Config = new TelegrambotConfigModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $Config->where('id',$info['id'])->find();
            $Config->update($info);
            $res = (new BotFactory())->set_run();
            $msg = '';$code = 1;
            if($res['code'] == 0){
                $code = 0;$msg = $res['msg'];
            }
            return Respons::run($code,'',$msg,$data,$info,$Config,lang('TelegramBot配置修改'));
        }
        if (isset($param['find'])) {
            $res=$Config->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            unset($info['id']);
            if(empty($info['text']) || empty($info['type']) || empty($info['chat_id']) || empty($info['lang'])){
                return Respons::run(0,'',lang('请输入完整信息'));
            }
            $Message = new TelegrambotMessageModel();
            $infos = $Message->where(['type'=>$info['type'],'chat_id'=>$info['chat_id']])->value('id');
            if($infos){
                return Respons::run(0,'',lang('发送目标重复'));
            }
            $info['create_time'] = time();
            $info['lang'] = strtolower($info['lang']);
            $res = $Message->save($info);
            $info['id'] = $Message->id;
            return Respons::run($res?1:0,'','','',$info,new TelegrambotMessageModel(),lang('Telegrambot消息添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $Message = new TelegrambotMessageModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            if(empty($info['text']) || empty($info['type']) || empty($info['chat_id'])){
                return Respons::run(0,'',lang('请输入完整信息'));
            }
            $infos = $Message->where(['type'=>$info['type'],'chat_id'=>$info['chat_id']])->where('id','<>',$info['id'])->value('id');
            if($infos){
                return Respons::run(0,'',lang('发送目标重复'));
            }
            $data = $Message->where('id',$info['id'])->find();
            $info['lang'] = strtolower($info['lang']);
            $res=$Message->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$Message,lang('Telegrambot消息修改'));
        }
        if (isset($param['find'])) {
            $res=$Message->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $Message =new TelegrambotMessageModel();
            $res=$Message->where("id",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$Message,lang('Telegrambot消息删除'));
        }
    }

    //消息测试
    public function sendtest(Request $request){
        $param = $request->param();
        $id=$param['id'] ?? 0;
        $BotFactory = new BotFactory();
        $ste = 0;
        $cofig = TelegrambotConfigModel::where('id',1)->find();
        if(empty($cofig['api_key']) || empty($cofig['bot_name'])){
            return Respons::run(0,'',lang('TelegramBot未配置'));
        }
        $one = TelegrambotMessageModel::where(['id'=>$id,'status'=>1])->find();
        if(empty($one)){
            return Respons::run(0,'',lang('消息已禁用'));
        }
        $rang_id = 0;
        if($one['type'] == 1){  //充值申请
            $rang_id = Db::name('user_offline')->where([['status','in',[-1,1]]])->orderRaw("rand()")->value('id');
            $ste = 2;
        }else if($one['type'] == 2){  //充值成功
            $rang_id = Db::name('user_offline')->where([['status','=',3]])->orderRaw("rand()")->value('id');
            $ste = 4;
        }else if($one['type'] == 3){  //提现申请
            $rang_id = Db::name('user_withdrawal')->where([['status','=',1]])->orderRaw("rand()")->value('id');
            $ste = 3;
        }else if($one['type'] == 4){  //提现成功
            $rang_id = Db::name('user_withdrawal')->where([['status','=',3]])->orderRaw("rand()")->value('id');
            $ste = 5;
        }else if($one['type'] == 5){  //游戏派彩
            $ste = 1;
        }
        if(in_array($ste,[2,3,4,5])){
            if(!$rang_id){
                return Respons::run(0,'',lang('暂无数据可发送消息'));
            }
        }
        $BotFactory->AutonMessage($ste,$rang_id,$one);
        return Respons::run(1);
    }
}