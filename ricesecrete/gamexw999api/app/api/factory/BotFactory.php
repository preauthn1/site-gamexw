<?php
namespace app\api\factory;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\service\BetService;
use app\common\service\UserWithdrawalService;
use app\common\Utils;
use Carbon\Carbon;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use think\facade\Lang;
use think\facade\Db;
use think\facade\Cache;
use app\common\model\TelegrambotConfigModel;
use app\common\model\TelegrambotMessageModel;



class BotFactory
{
    private $api_key = '';
    private $username = "";
    private $param;//请求参数

    public function __construct(){
        $this->param=\think\facade\Request::param();
        $config = TelegrambotConfigModel::where('id',1)->find();
        if(empty($config)){
            return;
        }
        $this->api_key = $config['api_key'];
        $this->username = $config['bot_name'];
    }

    //设置回调地址
    public function set_run(){
        try {
            $url = getWebUrl() . "/bot/webhook";
            $api = new Telegram($this->api_key, $this->username);
            $result = $api->setWebhook($url);
            if($result->isOk()){
                return ['code' => 1,'msg' => 'success'];
            }else{
                return ['code' => 0,'msg' => 'fail'];
            }
        } catch (TelegramException $e) {
            return ['code' => 0,'msg' => $e->getMessage()];
        }
    }

    //删除回调地址
    public function del_run(){
        try {
            $api = new Telegram($this->api_key, $this->username);
            $result = $api->deleteWebhook();
            if($result->isOk()){
                return ['code' => 1,'msg' => 'success'];
            }else{
                return ['code' => 0,'msg' => 'fail'];
            }
        } catch (TelegramException $e) {
            return ['code' => 0,'msg' => $e->getMessage()];
        }
    }

    //处理指令消息
    public function hook_run() {
        try {
            $param = $this->param;
            $api = new Telegram($this->api_key, $this->username);
            if(env('bot.log_status',0)){
                Utils::L('bot', $param);
            }
            $paths = [
                root_path('app/common/telegram'),
            ];
            $api->addCommandsPaths($paths);
            $api->handle();
        } catch (Exception $exc) {
            Utils::L('bot_error', [$exc->getMessage(), $exc->getTraceAsString()]);
        }
    }

    public function AutonMessage($status = 1,$oid = 0,$one = '') {
        if( !$this->api_key || !$this->username ){
            return true;
        }
        try {
            new Telegram($this->api_key, $this->username);
            if($status == 1){
                $this->bets($one);
            }else if($status == 2){
                $this->OfflineMsg($oid,$one);
            }else if($status == 3){
                $this->WithdrawalMsg($oid,$one);
            }else if($status == 4){
                //线上充值成功
                $this->OlineSuccessMsg($oid,$one);
            }else if($status == 5){
                //提现成功
                $this->WithdrawalSuccessMsg($oid,$one);
            }
        } catch (Exception $exc) {
            Utils::L('bot_error', [$exc->getMessage(), $exc->getTraceAsString()]);
        }
    }

    //游戏派彩
    public function bets($one){
        $list[] = $one;
        if(empty($one)){
            $list = TelegrambotMessageModel::where(['type'=>5,'status'=>1])->select();
        }
        if(empty($list)){
            return;
        }

        $startTime = strtotime("-30 minute");//前30分钟
        $endTime = time();
        $where = [['a.gb_winlose','=',3], ['t.tc_status','=',1]];
        $betWhere = BetService::getGbidByTime($startTime, $endTime, 'gb_bettime', 'a')['sql'];
        $currency = Util::getCurrencyDefaultSymbol('symbol');

        foreach ($list as $k=>$v){
            $content = '';
            $count = $v['number'];
            $min_price = $v['min_price'];
            $max_price = $v['max_price'];
            $chat_id = $v['chat_id'];
            $range = $v['lang'];
            Lang::load(app()->getRootPath() . 'app/lang/'.$range.'.php');

            $list = Db::name('game_bets')->alias("a")->where($where)->where($betWhere)
                ->join("dq_user u","a.gb_uid=u.id")
                ->join("dq_game_infos g","a.gb_gid=g.gi_gid")
                ->join("dq_games ge","ge.g_id=a.gb_gid")
                ->join("dq_third_configs t","ge.g_web_tcid=t.tc_id")
                ->field('u.username,g.gi_name,a.gb_winprice,t.tc_name')
                ->order('a.gb_id desc')
                ->limit($count)
                ->select();
            $list_count = count($list->toArray());
            $num = $count - $list_count;
            for($i=1; $i<=$num; $i++) {
                $name = get_rand_char(10);
                $len = mb_strlen($name);
                $str1 = mb_substr($name,0,1);
                $str2 = mb_substr($name,$len-2,1);
                $game =  Db::name('game_infos')->alias("a")->where([['t.tc_status','=',1]])
                    ->join("dq_games g","a.gi_gid=g.g_id")
                    ->join("dq_third_configs t","g.g_web_tcid=t.tc_id")
                    ->field('a.gi_name,t.tc_name')
                    ->orderRaw("rand()")
                    ->limit(1)
                    ->find();
                $username = $str1.'****'.$str2;
                $amount = rand_float($min_price,$max_price);
                $msg_data = ['third'=>$game['tc_name'],'game'=>$game['gi_name'],'username'=>$username,'amount'=>$amount,'currency'=>$currency];
                $text = "{$v['text']}";
                $content .= lang::get($text, $msg_data)."\n";
            }

            if($list_count >= 1){
                foreach ($list as $v1){
                    $len = mb_strlen($v1['username']);
                    $str1 = mb_substr($v1['username'],0,1);
                    $str2 = mb_substr($v1['username'],$len-2,1);
                    $game = $v1['gi_name'];
                    $third = $v1['tc_name'];
                    $username = $str1.'****'.$str2;
                    $amount = sprintf("%.2f",$v1['gb_winprice']);
                    $msg_data = ['third'=>$third,'game'=>$game,'username'=>$username,'amount'=>$amount,'currency'=>$currency];
                    $text = "{$v['text']}";
                    $content .= lang::get($text, $msg_data)."\n";
                }
            }

            if($content) {
                $data = [
                    'chat_id' => $chat_id,
                    'text' => $content,
                    'parse_mode' => 'HTML',
                ];
                $result = Request::sendMessage($data);
                $this->log($result);
            }
        }

    }

    //充值通知
    public function OfflineMsg($oid,$one){
        $list[] = $one;
        if(empty($one)){
            $list = TelegrambotMessageModel::where(['type'=>1,'status'=>1])->select();
        }
        if(empty($list)){
            return;
        }

        $where = [['a.status','in',[-1,1]],['u.status','=',1],['a.id','=',$oid]];
        $info = Db::name('user_offline')->alias("a")->where($where)
            ->join("user u","a.uid=u.id")
            ->field('a.id,a.order_sn,a.apply_price,a.add_time,u.id as uid,u.username,u.phone,u.rec_code,u.real_name')
            ->find();

        if($info){
            foreach ($list as $k=>$v) {
                $content = '';
                $chat_id = $v['chat_id'];
                $range = $v['lang'];
                $msg_data = ['uid'=>$info['uid'],'username'=>$info['username'],'real_name'=>$info['real_name'],'phone'=>$info['phone'],'order_sn'=>$info['order_sn'],'apply_price'=>$info['apply_price'],'add_time'=>date('Y-m-d H:i:s',$info['add_time'])];
                $text_data = self::preg_match($v['text']);
                foreach ($text_data as $vv){
                    if(!isset($msg_data[$vv])){
                        $msg_data[$vv] = $this->GetData($vv,$info['uid'],$range);
                    }
                }
                $text = "{$v['text']}";
                Lang::load(app()->getRootPath() . 'app/lang/'.$range.'.php');
                $content = lang::get($text, $msg_data);
                $content = str_replace('=', "\n", $content);
                if($content){
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $content,
                        'parse_mode' => 'HTML',
                    ];
                    $result = Request::sendMessage($data);
                    $this->log($result);
                }
            }
        }
    }

    //线上成功充值通知
    public function OlineSuccessMsg($oid,$one){
        $list[] = $one;
        if(empty($one)){
            $list = TelegrambotMessageModel::where(['type'=>2,'status'=>1])->select();
        }
        if(empty($list)){
            return;
        }
        $where = [['a.status','=',3],['u.status','=',1],['a.id','=',$oid]];
        $info = Db::name('user_offline')->alias("a")->where($where)
            ->join("user u","a.uid=u.id")
            ->field('a.id,a.order_sn,pay_id,a.apply_price,a.add_time,u.id as uid,u.username,u.phone,u.rec_code,u.real_name')
            ->find();

        if($info){
            $pay_names = PaySetModel::column('pay_name', 'id');
            $payName = $pay_names[$info['pay_id']] ?? '';

            foreach ($list as $k=>$v) {
                $chat_id = $v['chat_id'];
                $range = $v['lang'];
                $msg_data = ['uid' => $info['uid'], 'username' => $info['username'], 'channel_name' => $payName, 'real_name' => $info['real_name'], 'phone' => $info['phone'], 'order_sn' => $info['order_sn'], 'apply_price' => $info['apply_price'], 'add_time' => date('Y-m-d H:i:s', $info['add_time'])];
                $text_data = self::preg_match($v['text']);
                foreach ($text_data as $vv){
                    if(!isset($msg_data[$vv])){
                        $msg_data[$vv] = $this->GetData($vv,$info['uid'],$range);
                    }
                }
                $text = "{$v['text']}";
                Lang::load(app()->getRootPath() . 'app/lang/' . $range . '.php');
                $content = lang::get($text, $msg_data);
                $content = str_replace('=', "\n", $content);
                if($content){
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $content,
                        'parse_mode' => 'HTML',
                    ];
                    $result = Request::sendMessage($data);
                    $this->log($result);
                }
            }
        }
    }


    //提现通知
    public function WithdrawalMsg($oid,$one){
        $list[] = $one;
        if(empty($one)){
            $list = TelegrambotMessageModel::where(['type'=>3,'status'=>1])->select();
        }
        if(empty($list)){
            return;
        }
        $where = [['a.status','=',1],['u.status','=',1],['a.id','=',$oid]];
        $info = Db::name('user_withdrawal')->alias("a")->where($where)
            ->join("user u","a.uid=u.id")
            ->join('user_bank ub', 'ub.uid = a.uid')
            ->field('a.id,a.order_sn,a.arrival_prices,a.arrival_price,a.add_time,u.id as uid,u.username,u.phone,u.rec_code,ub.bank_name,ub.bank_hm')
            ->find();

        if($info){
            $bank_code = UserWithdrawalService::getBankCodeByBankName($info['bank_name']);
            foreach ($list as $k=>$v) {
                $chat_id = $v['chat_id'];
                $range = $v['lang'];
                $msg_data = ['uid'=>$info['uid'],'username'=>$info['username'],'bank_name'=>$info['bank_name'],'bank_code'=>$bank_code,'real_name'=>$info['bank_hm'],'phone'=>$info['phone'],'order_sn'=>$info['order_sn'],'arrival_prices'=>$info['arrival_prices'],'arrival_price'=>$info['arrival_price'],'withdrawal_add_time'=>date('Y-m-d H:i:s',$info['add_time'])];
                $text_data = self::preg_match($v['text']);
                foreach ($text_data as $vv){
                    if(!isset($msg_data[$vv])){
                        $msg_data[$vv] = $this->GetData($vv,$info['uid'],$range);
                    }
                }
                $text = "{$v['text']}";
                Lang::load(app()->getRootPath() . 'app/lang/'.$range.'.php');
                $content = lang::get($text, $msg_data);
                $content = str_replace('=', "\n", $content);
                if($content){
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $content,
                        'parse_mode' => 'HTML',
                    ];
                    $result = Request::sendMessage($data);
                    $this->log($result);
                }
            }
        }
    }

    //提现通知
    public function WithdrawalSuccessMsg($oid,$one){
        $list[] = $one;
        if(empty($one)){
            $list = TelegrambotMessageModel::where(['type'=>4,'status'=>1])->select();
        }
        if(empty($list)){
            return;
        }
        $where = [['a.status','=',3],['u.status','=',1],['a.id','=',$oid]];
        $info = Db::name('user_withdrawal')->alias("a")->where($where)
            ->join("user u","a.uid=u.id")
            ->join('user_bank ub', 'ub.uid = a.uid')
            ->field('a.id,a.order_sn,a.method_id,a.arrival_prices,a.arrival_price,a.add_time,u.id as uid,u.username,u.phone,u.rec_code,ub.bank_name,ub.bank_hm')
            ->find();

        if($info){
            //获取支付渠道获取提现银行卡的银行编码
            $bank_code = UserWithdrawalService::getBankCodeByBankName($info['bank_name']);
            //支付渠道名称
            $pay_names = PaySetModel::column('pay_name', 'id');
            $pay_name = $info['bank_hm'];#默认取线下
            if( $info['method_id'] > 0 ){
                //线上渠道
                $pay_name = $pay_names[$val['method_id']] ?? '';
            }
            foreach ($list as $k=>$v) {
                $chat_id = $v['chat_id'];
                $range = $v['lang'];
                $msg_data = ['uid'=>$info['uid'],'username'=>$info['username'],'real_name'=>$info['bank_hm'],'channel_name'=>$pay_name,'bank_name'=>$info['bank_name'],'bank_code'=>$bank_code,'phone'=>$info['phone'],'order_sn'=>$info['order_sn'],'arrival_prices'=>$info['arrival_prices'],'arrival_price'=>$info['arrival_price'],'withdrawal_add_time'=>date('Y-m-d H:i:s',$info['add_time'])];
                $text_data = self::preg_match($v['text']);
                foreach ($text_data as $vv){
                    if(!isset($msg_data[$vv])){
                        $msg_data[$vv] = $this->GetData($vv,$info['uid'],$range);
                    }
                }
                $text = "{$v['text']}";
                Lang::load(app()->getRootPath() . 'app/lang/'.$range.'.php');
                $content = lang::get($text, $msg_data);
                $content = str_replace('=', "\n", $content);
                if($content){
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $content,
                        'parse_mode' => 'HTML',
                    ];
                    $result = Request::sendMessage($data);
                    $this->log($result);
                }
            }
        }
    }



    public function log($result) {
        $count = env('bot.log_status',0);
        if (!$result->isOk()) {
            Utils::L('bot_error', $result);
        }
        if($count) {
            Utils::L('bot_message', $result);
        }
    }

    public function content($text) {
        return str_replace(
            ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!', ],
            ['\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!', ],
            $text);
    }

    /*获取数据
     * $key 标识
     * $uid 用户id
     * $lang 语言
     * */
    public function GetData($key,$uid = 0,$lang = 'cn'){
        //用户余额
        if($key == 'balance'){
            $info['balance'] = Db::name('user')->where(['id'=>$uid])->value('balance');
        }

        //今日总充值
        if($key == 'today_total_price'){
            $where2 = [
                ['update_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp]],
                ['status', 'in', '3,4']
            ];
            $info['today_total_price'] = UserOfflineModel::where($where2)->sum('arrival_price');
        }

        //上次存款金额
        if($key == 'last_money'){
            $info['last_money'] = Db::name('user_offline')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->order('add_time desc')->value('arrival_price') ??0;
        }

        //总存款
        if($key == 'total_deposit'){
            $info['total_deposit'] = Db::name('user_offline')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->sum('arrival_price');
        }

        //上次存款时间
        if($key == 'last_deposit_time'){
            $last_deposit_time = Db::name('user_offline')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->order('add_time desc')->value('add_time');
            $info['last_deposit_time'] = empty($last_deposit_time) ? '' : date('Y-m-d H:i:s', $last_deposit_time);
        }

        //今日总提现
        if($key == 'withdrawal_today_total_price'){
            $where2 = [
                ['update_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp]],
                ['status', 'in', '3,4']
            ];
            $info['withdrawal_today_total_price'] = UserWithdrawalModel::where($where2)->sum('arrival_price');
        }

        //上次提款时间
        if($key == 'last_withdrawal_time'){
            $last_withdrawal_time = Db::name('user_withdrawal')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->order('add_time desc')->value('add_time');
            $info['last_withdrawal_time'] = empty($last_withdrawal_time) ? '' : date('Y-m-d H:i:s', $last_withdrawal_time);
        }

        //上次提款金额
        if($key == 'last_withdrawal_amount'){
            $info['last_withdrawal_amount'] = Db::name('user_withdrawal')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->order('add_time desc')->value('arrival_price') ??0;
        }

        //存提差
        if($key == 'difference'){
            $total_deposit = Db::name('user_offline')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->sum('arrival_price');
            $total_withdrawal = Db::name('user_withdrawal')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->sum('arrival_price');
            $info['difference'] = $total_deposit - $total_withdrawal;
        }

        //总提现
        if($key == 'total_withdrawal'){
            $info['total_withdrawal'] = Db::name('user_withdrawal')->where([['uid' ,'=', $uid],['status', 'in', '3,4']])->sum('arrival_price');
        }

        //平均投注金額
        if($key == 'average_betting_amount'){
            $total_betprice = Db::name('game_bets')->where([['gb_uid' ,'=', $uid],['gb_winlose' ,'<>', 0]])->sum('gb_price');
            $total_betcount = Db::name('game_bets')->where([['gb_uid' ,'=', $uid,['gb_winlose' ,'<>', 0]]])->count();
            if($total_betprice > 0 && $total_betcount > 0){
                $info['average_betting_amount'] = round($total_betprice/$total_betcount,4);
            }else{
                $info['average_betting_amount'] = 0;
            }
        }

        //平均派彩金額
        if($key == 'average_payout_amount'){
            $average_betprice = Db::name('game_bets')->where([['gb_uid' ,'=', $uid],['gb_winlose' ,'=', 3]])->sum('gb_winprice');
            $average_betcount = Db::name('game_bets')->where([['gb_uid' ,'=', $uid,['gb_winlose' ,'<>', 0]]])->count();
            if($average_betprice > 0 && $average_betcount > 0){
                $info['average_payout_amount'] = round($average_betprice/$average_betcount,4);
            }else{
                $info['average_payout_amount'] = 0;
            }
        }

        //上次登入IP
        if($key == 'last_loginip'){
            $info['last_loginip'] = Db::name('user')->where(['id'=>$uid])->value('end_login_ip');
        }

        //本次登录IP
        if($key == 'this_loginip'){
            $info['this_loginip'] = Util::get_ip();
        }

        //同IP登入数量
        if($key == 'same_loginip'){
            $end_login_ip = Db::name('user')->where(['id'=>$uid])->value('end_login_ip');
            $info['same_loginip'] = Db::name('user')->where(['end_login_ip'=>$end_login_ip,'status'=>1])->count();
        }

        //注册时间
        if($key == 'register_time'){
            $info['register_time'] = date('Y-m-d H:i:s',Db::name('user')->where(['id'=>$uid])->value('add_time'));
        }

        //存款优惠金额
        if($key == 'discount_amount'){
            $info['discount_amount'] = Db::name('user_offline')->alias("a")->where(['a.uid'=>$uid,'a.first'=>1])
                ->join("action_apply d","d.uid=a.uid and d.add_time > a.add_time")
                ->sum('ck_hl_amount');
        }

        //游戏分类注单数
        if($key == 'gametype_bets'){
            Lang::load(app()->getRootPath() . 'app/lang/'.$lang.'.php');
            $data = [];
            $game_type = Db::name('game_type')->where([['state','=',1]])->select();
            foreach($game_type as $k=>$v){
                $count = Db::name('games')->alias('a')->where([['a.g_type', '=', $v['id']], ['a.g_state', '=', 1]])
                    ->join('game_bets b', 'a.g_id = b.gb_gid')
                    ->where([['b.gb_uid', '=', $uid], ['b.gb_winlose', '<>', 0]])
                    ->count();

                if ($count > 0) {
                    $data[lang::get($v['name'])] = $count;
                }
            }
            $string = '';
            foreach ($data as $k => $v) {
                $string .= "\n".$k . ':' . $v;
            }
            $info['gametype_bets'] = $string;
        }

        //最大盈利倍數
        if($key == 'maximum_profit_multiple'){
            $maximum_list = Db::name('game_bets')
                ->where([['gb_uid', '=', $uid], ['gb_winlose', '<>', 0]])
                ->select();
            $max_profit_multiple = 0;
            foreach ($maximum_list as $bet) {
                if ($bet['gb_price'] > 0) { // 防止除以零
                    $profit_multiple = ($bet['gb_winprice'] - $bet['gb_price']) / $bet['gb_price'];
                    if ($profit_multiple > $max_profit_multiple) {
                        $max_profit_multiple = $profit_multiple; // 更新最大盈利比率
                    }
                }
            }
            $info['maximum_profit_multiple'] = round($max_profit_multiple,2); // 存储最大盈利比率
        }

        //最大盈利金額
        if($key == 'maximum_profit_amount'){
            $info['maximum_profit_amount'] = Db::name('game_bets')->where([['gb_uid' ,'=', $uid],['gb_winlose' ,'<>', 0]])->max('gb_winprice');
        }


        if(isset($info['add_time'])){
            $info['add_time'] = date('Y-m-d H:i:s',$info['add_time']);
        }
        return isset($info[$key]) ? $info[$key] : '';
    }

    public static function preg_match($text){
        preg_match_all('/{:(\w+)}/', $text, $matches);
        return $matches[1];
    }

}