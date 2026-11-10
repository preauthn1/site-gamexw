<?php
namespace app\api\factory;
use app\common\model\GameSetModel;
use app\common\model\GameTxModel;
use app\common\model\GameZhudanModel;
use app\common\model\GameReportOperatingModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\redis\CommomRedis;
use think\facade\Request;
use think\facade\Db;
use think\facade\Log;
 class FlyerFactory
{
    use GameZhudanFactory;
    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->param = Request::param();
        $this->gid = $this->param['gid']??'';
        $this->uid = $uid;
        $this->bs = 100;//金额和分数比例
        //用户信息id
        $this->uinfo=UserModel::where("id",$this->uid)->field('id,username,balance,lebal,flyer_money')->find();
        //渠道信息
        if($this->gid == ''){
            $game_name = 'Pilot';
        }else{
            $game_name = '';
        }
        $game = GameSetModel::get_game_platform($this->gid,$game_name);
        $this->gid = $game['id'];
        $this->channel = $game['config'];
        //echo '<pre>';print_r($game['config']);die;

        //登录
        $this->login_url = $this->channel['api_url'].'/api/login';
        //上分
        $this->in_url = $this->channel['api_url'].'/api/transfer/in';
        //下分
        $this->out_url = $this->channel['api_url'].'/api/transfer/out';
        //查询用户当前余额
        $this->amount_url = $this->channel['api_url'].'/api/transfer/getUserAmount';
        //查询上下分订单
        $this->transfer_url = $this->channel['api_url'].'/api/order/transfer';
        //查询注单记录
        $this->record_url = $this->channel['api_url'].'/api/order/gameRecord';
        //查询用户是否存在
        $this->check_user_url = $this->channel['api_url'].'/api/userExist';
        //创建用户
        $this->create_user_url = $this->channel['api_url'].'/api/createUser';
        //报表数据
        $this->report_url = $this->channel['api_url'].'/api/order/getChannelSumRecord';
    }

    //登录飞行员
    public function login_run(){
        $info = $this->param;
        $time = time();
        $signmd5 = 'channel='.$this->channel['channel'].'&currency='.$this->channel['currency'].'&expand='.$this->uinfo['lebal'].'&timestamp='.$time.'&username='.$this->uinfo['username'].'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'timestamp'=>$time,
            'currency'=>$this->channel['currency'],
            'username'=>$this->uinfo['username'],
            'expand'=>$this->uinfo['lebal'],
            'sign'=>md5($signmd5)
        );

        $res = Util::postRequest($this->login_url,$post);
        $res = json_decode($res,true);
        if($res['code'] == 0){
            $rsps = $res['data'];
        }else{
            $rsps = [];
        }

        $rsps['code']=1;
        return $rsps;
    }

    //个人中心
    public function list_run(){
        $username = $this->uinfo['username'];
//        $username = 'yyp';
        //定义开始时间
        $yesterday_start_time =strtotime(date('Y-m-d H:i:s',strtotime("-1 day 00:00:00")));//昨日开始时间
        $today_start_time = strtotime(date('Y-m-d H:i:s',strtotime(" 00:00:00")));//今日开始时间
        //定义结束时间
        $yesterday_end_time = strtotime(date('Y-m-d H:i:s',strtotime("-1 day 23:59:59")));//昨日结束时间
        $today_end_time = strtotime(date('Y-m-d H:i:s',strtotime(" 23:59:59")));//今日结束时间

        //初始化收益
        $profit = [];
        //设置缓存
//        $end_strat_time = md5("flyer_factory".$username);
//        $CommomRedis = new CommomRedis();
//        if(!$CommomRedis->has($end_strat_time)) {

        //获取各个用户昨日投注、昨日收益;今日投注、今日收益;及总投注、总收益
        $sql = 'SELECT  	
                SUM( CASE WHEN add_time between ' . $yesterday_start_time . ' and ' . $yesterday_end_time . ' and username =' . "'" .$username . "'" .' THEN IFNULL(`bets_coin`,0) ELSE 0 END) AS yesterday_bets_total, 	       
                SUM( CASE WHEN add_time between ' . $yesterday_start_time . ' and ' . $yesterday_end_time . ' and username =' ."'" . $username . "'" .' THEN IFNULL(`reward_coin`,0) ELSE 0 END) AS yesterday_reward_total, 	
                SUM( CASE WHEN  add_time between ' . $today_start_time . ' and ' . $today_end_time . ' and username =' . "'" .$username . "'" .' THEN IFNULL(`bets_coin`,0) ELSE 0 END) AS today_bets_total, 	
                SUM( CASE WHEN add_time between ' . $today_start_time . ' and ' . $today_end_time . ' and username =' ."'" . $username . "'" .' THEN IFNULL(`reward_coin`,0) ELSE 0 END) AS today_reward_total,
                SUM( CASE WHEN  username =' ."'" .$username . "'" .' THEN IFNULL(`bets_coin`,0) ELSE 0 END) AS all_bets_total, 	
                SUM( CASE WHEN  username =' ."'" . $username . "'" .' THEN IFNULL(`reward_coin`,0) ELSE 0 END) AS all_reward_total 
                FROM 	`dq_flyer_zhudan`';
        $total_data = Db::query($sql);

        if(isset($total_data[0])){
            $profit['yesterday_total'] = bcsub($total_data[0]['yesterday_reward_total'],$total_data[0]['yesterday_bets_total'],2);
            $profit['today_total'] = bcsub($total_data[0]['today_reward_total'],$total_data[0]['today_bets_total'],2);
            $profit['all_total'] = bcsub($total_data[0]['all_reward_total'],$total_data[0]['all_bets_total'],2);
        }else{
            $profit['yesterday_total'] = 0;
            $profit['today_total'] = 0;
            $profit['all_total'] = 0;
        }

        //$CommomRedis->set($end_strat_time, json_encode($profit), 300);
//        }else{
//            $profit = $CommomRedis->get($end_strat_time);
//            $profit = json_decode($profit,true);
//        }
        $rsps['code'] = 1;
        $rsps['data']['total'] = $profit;

        $rsps['data']['userinfo'] = $this->uinfo;
        return $rsps;
    }

    //注单列表
    public function zhudan_run(){
        $username = $this->uinfo['username'];
        $info = $this->param;
        $pagenow = $info['page']??1;
        $limit = $info['limit']??10;
        $where[]=["a.username","=",$username];
        $FlyerZhudanModel =new GameZhudanModel();
        $rsps['data']=$FlyerZhudanModel
            ->alias("a")
            ->where($where)
            ->limit((($pagenow-1)*$limit),$limit)
            ->order("id desc")
            ->select();
        $rsps['code']=1;
        return $rsps;
    }

    //下分
    public function flyer_out_run(){
        //在线下分停用
        die;
        $param = $this->param;

        //isFull,是否全额转出，1：是，0：否； 该值为1时，money请传0
        if(isset($param['price'])){
            $price = $param['price'];
            $isFull = 0;
        }else{
            $price = 0;
            $isFull = 1;
        }
        if(!is_numeric($price)){
            ajax_return("Amount formatted incorrectly", 0);
        }
        if(!$price){
            ajax_return("Please enter the transfer amount", 0);
        }
        if($price < 0){
            ajax_return("Transfer in amount format error", 0);
        }

        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("flyerOut".$this->uid)) {
            ajax_return("请勿频繁访问", 0);
        }

        $sys = $ApiRedis->systemInfo();
        Db::startTrans();
        try {
            $userlock = UserModel::where("id",$this->uid)->lock(true)->field("flyer_money")->find();
            if (!($userlock['flyer_money'] >= $price)) {
                ajax_return("Insufficient balance", 0);
            }

            $add['type'] = 2;
            $add['uid'] = $this->uid;
            $add['username'] = $this->uinfo['username'];
            $add['lebal'] = $this->uinfo['lebal'];
            $add['money'] = $price; //
            $add['channel'] = $this->channel['channel']; //
            $add['order_id'] = md5(uniqid(rand().'_',true));
            $add['add_time'] = time();
            $add['game_name'] = 'Pilot';
            $id = FlyerOrderModel::insertGetId($add);

            //飞行员类型
            $cz_type = 66;

            UserMsgModel::add_msg($this->uid,"飞行员下分",'下分成功,金额 {:price}',14,$id,['price'=>Util::i18n_number_format($price)]);
            UserModel::cz_balance($this->uid,$price,"+", true,"飞行员下分", $cz_type,1,2);

            //请求第三方下分
            $time = time();
            $price = bcmul($price,$this->bs,0);
            $signmd5 = 'channel='.$this->channel['channel'].'&isFull=0&money='.$price.'&orderId='.$add['order_id'].'&timestamp='.$time.'&username='.$this->uinfo['username'].'&key='.$this->channel['key'];

            $post = array(
                'channel'=>$this->channel['channel'],
                'timestamp'=>$time,
                'username'=>$this->uinfo['username'],
                'isFull'=>$isFull,
                'money'=>$price,
                'orderId'=>$add['order_id'],
                'sign'=>md5($signmd5)
            );

            $res = Util::postRequest($this->out_url,$post);
            $res = json_decode($res,true);
            if($res['code'] != 0){
                ajax_return($res['msg'], 0);
            }
            // 提交事务
            Db::commit();
            $rsps['data'] = ['id'=>$id];
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    //下分全部
    public function flyer_out_all_run(){
        $param = $this->param;
        $username = $param['username']??'';

        if($username == ''){
            ajax_return("Param is error", 0);
        }

        //验证用户是否存在
        $uinfo=UserModel::where("username",$username)->field('id,username,lebal')->find();
        if(!isset($uinfo['username']) || $uinfo['username'] == ''){
            ajax_return("Param is error1", 0);
        }
        //isFull,是否全额转出，1：是，0：否； 该值为1时，money请传0
        $price = 0;
        $isFull = 1;

        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("flyerOutAll".$uinfo['id'])) {
            ajax_return("请勿频繁访问", 0);
        }

        $sys = $ApiRedis->systemInfo();
        Db::startTrans();
        try {

            //请求第三方下分
            $time = time();
            $order_id = md5(uniqid(rand().'_',true));
            $signmd5 = 'channel='.$this->channel['channel'].'&isFull='.$isFull.'&money='.$price.'&orderId='.$order_id.'&timestamp='.$time.'&username='.$uinfo['username'].'&key='.$this->channel['key'];

            $post = array(
                'channel'=>$this->channel['channel'],
                'timestamp'=>$time,
                'username'=>$uinfo['username'],
                'isFull'=>$isFull,
                'money'=>$price,
                'orderId'=>$order_id,
                'sign'=>md5($signmd5)
            );

            Log::write([$uinfo['username'].'全部下分请求', "Pilot", $post], "payment");
            $res = Util::postRequest($this->out_url,$post);
            Log::write([$uinfo['username'].'全部下分返回', "Pilot", $res], "payment");
            $res = json_decode($res,true);
            if($res['code'] !== 0){
                ajax_return($res['msg'], 0);
            }

            $price = bcdiv($res['data']['money'],$this->bs,2);

            $add['tx_type'] = 2;//上分类型 2上分
            $add['uid'] = $uinfo['id'];
            $add['game_id'] = $this->gid;
            $add['bet_status'] = 3;//已经结算
            $add['bet_amount'] = $price;
            $add['actual_amount'] = $price;
            $add['payout'] = $price;
            $add['ref_id'] = $uinfo['lebal'];//用户标签
            $add['product_name'] = $this->channel['channel']; //渠道
            $add['tx_number'] = md5(uniqid(rand().'_',true));//唯一订单号
            $add['add_time'] = time();
            $add['game_name'] = 'Pilot';
            $id = GameTxModel::insertGetId($add);

            //飞行员类型
            $cz_type = 66;

            UserMsgModel::add_msg($uinfo['id'],"飞行员下分",'下分成功,金额 {:price}',14,$id,['price'=>Util::i18n_number_format($price)]);
            UserModel::cz_balance($uinfo['id'],$price,"+", true,"飞行员下分", $cz_type,1,2);


            // 提交事务
            Db::commit();
            $rsps['data'] = ['id'=>$id];
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    //上分
    public function flyer_in_run(){
        $param = $this->param;
        if(isset($param['price'])){
            die;//在线上分关闭
            $price = $param['price'];
        }else{
            $price = $this->uinfo['balance'];
        }

        //先检查用户是否存在
        $check_user = self::check_user_run();
        if($check_user['code'] == 304){

            //如果用户不在，就创建
            $create_user = self::create_user_run();
            if($create_user['code'] != 0){
                ajax_return("create user error", 0);
            }
        }

        if(!is_numeric($price)){
            ajax_return("Amount formatted incorrectly", 0);
        }
        if(!$price){
            ajax_return("Please enter the transfer amount", 0);
        }

        if($price <= 0){
            ajax_return("Transfer in amount format error", 1);
        }

        if($price > $this->uinfo['balance']){
            ajax_return("Insufficient balance", 0);
        }

        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("flyerIn".$this->uid)) {
            ajax_return("请勿频繁访问", 0);
        }

        $sys = $ApiRedis->systemInfo();
        Db::startTrans();
        try {

            $userlock = UserModel::where("id",$this->uid)->lock(true)->field("balance")->find();
            if (!($userlock['balance'] >= $price)) {
                ajax_return("Insufficient balance", 0);
            }

            //如果用户存在，就上分
            $add['tx_type'] = 1;//上分类型 1上分
            $add['uid'] = $this->uid;
            $add['game_id'] = $this->gid;
            $add['bet_status'] = 1;//已经结算
            $add['bet_amount'] = $price;
            $add['actual_amount'] = $price;
            $add['payout'] = $price;
            $add['ref_id'] = $this->uinfo['lebal'];//用户标签
            $add['product_name'] = $this->channel['channel']; //渠道
            $add['tx_number'] = md5(uniqid(rand().'_',true));//唯一订单号
            $add['add_time'] = time();
            $add['game_name'] = 'Pilot';
            $id = GameTxModel::insertGetId($add);

            //飞行员类型
            $cz_type = 66;

            UserMsgModel::add_msg($this->uid,"飞行员上分",'上分成功,金额 {:price}',14,$id,['price'=>Util::i18n_number_format($price)]);
            UserModel::cz_balance($this->uid,$price,"-", true,"飞行员上分", $cz_type,1,2);

            //请求第三方上分
            $time = time();
            $price = bcmul($price,$this->bs,0);
            $signmd5 = 'channel='.$this->channel['channel'].'&money='.$price.'&orderId='.$add['tx_number'].'&timestamp='.$time.'&username='.$this->uinfo['username'].'&key='.$this->channel['key'];
            $post = array(
                'channel'=>$this->channel['channel'],
                'timestamp'=>$time,
                'username'=>$this->uinfo['username'],
                'money'=>$price,
                'orderId'=>$add['tx_number'],
                'sign'=>md5($signmd5)
            );
            Log::write([$post['username'].'全部上分请求', "Pilot", $post], "payment");
            $res = Util::postRequest($this->in_url,$post);
            Log::write([$post['username'].'全部上分返回', "Pilot", $res], "payment");
            $res = json_decode($res,true);
            if($res['code'] !== 0){
                ajax_return('Failed to score', 0);
            }

            // 提交事务
            Db::commit();
            $rsps['data'] = ['id'=>$id];
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    //余额同步
    public function flyer_balance_run(){
        $info = $this->param;
        $username = $info['username']??'';

        if($username == ''){
            ajax_return("Param is error", 0);
        }

        //验证用户是否存在
        $uinfo=UserModel::where("username",$username)->field('id,username')->find();
        if(!isset($uinfo['username']) || $uinfo['username'] == ''){
            ajax_return("Param is error1", 0);
        }
        $time = time();
        $signmd5 = 'channel='.$this->channel['channel'].'&timestamp='.$time.'&username='.$username.'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'timestamp'=>$time,
            'username'=>$username,
            'sign'=>md5($signmd5)
        );

        $res = Util::postRequest($this->amount_url,$post);
        $res = json_decode($res,true);
        Log::write(['flyer_balance_run',"Pilot",$res], "payment");
        if($res['code'] == 0){
            $rsps = $res['data'];
            //修改用户的飞行员余额
            $up = [];
            $up['id'] = $uinfo['id'];
            $up['flyer_money'] = bcdiv($res['data']['amount'],$this->bs,2);
            if(!UserModel::update($up)){
                $rsps['code']=1;
                $rsps['msg']='余额同步失败！';
            }else{
                $rsps['code']=0;
                $rsps['msg']='余额同步成功！';
            }
        }else{
            $rsps['code']=1;
            $rsps['msg']='同步接口请求失败！';
        }
        Log::write(['flyer_balance_run',"Pilot",$rsps], "payment");
        return $rsps;
    }

    //检查用户是否存在
    public function check_user_run(){

        $time = time();
        $username = $this->uinfo['username'];
        $signmd5 = 'channel='.$this->channel['channel'].'&timestamp='.$time.'&username='.$username.'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'timestamp'=>$time,
            'username'=>$username,
            'sign'=>md5($signmd5)
        );

        $res = Util::postRequest($this->check_user_url,$post);
        return json_decode($res,true);
    }

    //创建用户
    public function create_user_run(){
        $time = time();
        $signmd5 = 'channel='.$this->channel['channel'].'&currency='.$this->channel['currency'].'&expand='.$this->uinfo['lebal'].'&timestamp='.$time.'&username='.$this->uinfo['username'].'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'timestamp'=>$time,
            'currency'=>$this->channel['currency'],
            'username'=>$this->uinfo['username'],
            'expand'=>$this->uinfo['lebal'],
            'sign'=>md5($signmd5)
        );

        $res = Util::postRequest($this->create_user_url,$post);
        return json_decode($res,true);

    }

    //注单同步
    public function flyer_zd_run(){
        $end_time = time();
        $CommomRedis = new CommomRedis();
        $key_time = md5('flyer_add_zhudan');
        if(!$CommomRedis->has($key_time)) {
            //如果Reids挂掉，查最新的时间
            $FlyerZhudanModel=GameZhudanModel::order("id desc")->field('add_time')->find();
            //echo json_encode($FlyerZhudanModel);die;
            $start_time = $FlyerZhudanModel['add_time']??strtotime('20220418000000');
        }else{
            $start_time = $CommomRedis->get($key_time);
        }
        echo '开始时间：'.date('YmdHis',$start_time).'--结束时间'.date('YmdHis',$end_time);
        $signmd5 = 'channel='.$this->channel['channel'].'&end='.$end_time.'&start='.$start_time.'&timestamp='.$end_time.'&username='.$this->uinfo['username'].'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'start'=>$start_time,
            'end'=>$end_time,
            'timestamp'=>$end_time,
            'username'=>$this->uinfo['username'],
            'sign'=>md5($signmd5)
        );

        $res = Util::postRequest($this->record_url,$post);
        $res = json_decode($res,true);
        if($res['code'] == 0){
            //分批插入
            if(isset($res['data'][0])){
                $insert = self::batchInsert($res['data'],5000);
                $CommomRedis->set($key_time,$end_time,86400 * 30);
                $rsps['code']=1;
                $rsps['msg']='同步注单成功！';
            }else{
                $rsps['code']=1;
                $rsps['msg']='无注单，同步注单成功！';
            }

        }else{
            $rsps['code']=0;
            $rsps['msg']='同步接口请求失败！';
        }
        return $rsps;
    }
    /**@param $data array 要插入的数据
     *@param $each int 每次插入的条数
     */
    public function batchInsert($data,$each){
        $full_table_name = $this->getZhuDanTableByDay();
        $num = count($data);        // 数据总数
        $step = ceil( $num/$each);  // insert执行总次数
        $j = 1;
        $s = $step;
        $time = time();
        foreach ($data as $val){    // 目前仅仅是起到循环作用
            if($j > $step) break;
            $arr2 = array_slice($data, ($step - $s) * $each, $each);     // 每次取5000条
            $sql = "insert IGNORE into ".$full_table_name." (`zd_id`,`username`,`game_id`,`bets_coin`,`reward_coin`,`before_coin`,`after_coin`,`add_time`,`begin_time`,`end_time`,`game_name`,`lebal`) VALUES ";
            foreach($arr2 as $v){
                //扩展字段为空，默认为一般用户
                if(!isset($v['expand']) || empty($v['expand'])){
                    $v['expand'] = 1;
                }
                $v['id'] = 'Pilot_'.$v['id'];
                $betsCoin = bcdiv($v['betsCoin'],$this->bs,2);
                $rewardCoin = bcdiv($v['rewardCoin'],$this->bs,2);
                $beforeCoin = bcdiv($v['beforeCoin'],$this->bs,2);
                $afterCoin = bcdiv($v['afterCoin'],$this->bs,2);
                $sql .= "('".$v['id']."','".$v['username']."',".$v['gameId'].",".$betsCoin.",".$rewardCoin.",".$beforeCoin.",".$afterCoin.",".$v['createdTime'].",".$v['betTime'].",".$v['finishTime'].",'Pilot',".$v['expand']."),";     // 将sql语句拼接起来

            }
            $sql = rtrim($sql, ',');
            $result =  Db::query($sql);
            unset($sql);
            $j++;
            $s--;
        }
        return $result;
    }

    //统计报表
    public function flyer_report_run(){

        $param = $this->param;
        if(isset($param['date'])){
            $start_time = strtotime(date('Ymd000000',strtotime($param['date'])));
            $end_time = strtotime(date('Y-m-d 23:59:59'));
        }else{
            $start_time = strtotime(date('Y-m-d 00:00:00',strtotime('-1 day')));
            $end_time = strtotime(date('Y-m-d 23:59:59'));
        }

        $signmd5 = 'channel='.$this->channel['channel'].'&end='.$end_time.'&start='.$start_time.'&timestamp='.$end_time.'&key='.$this->channel['key'];

        $post = array(
            'channel'=>$this->channel['channel'],
            'start'=>$start_time,
            'end'=>$end_time,
            'timestamp'=>$end_time,
            'sign'=>md5($signmd5)
        );
        $res = Util::postRequest($this->report_url,$post);

        $res = json_decode($res,true);
        if($res['code'] == 0 && isset($res['data'][0]['betsNumber'])){

            foreach ($res['data'] as $k=>$v){
                //写入库
                $add['bets_number'] = $v['betsNumber'];
                $add['bets_total'] = bcdiv($v['bets'],$this->bs,2);
                $add['pay_total'] = bcdiv($v['payOut'],$this->bs,2);
                $add['user_total_loss'] = bcdiv($v['playerWl'],$this->bs,2);
                $add['platform_total_lose'] = bcdiv($v['platformWl'],$this->bs,2);
                $add['channel'] = $v['channel'];
                $add['add_time'] = $v['createdTime'];
                $add['day_time'] = date('Y-m-d',$v['createdTime']);
                $add['game_name'] = 'Pilot';
                $find = GameReportOperatingModel::where("day_time",$add['day_time'])->where("game_name",$add['game_name'])->field('id')->find();
                if(isset($find['id'])){
                    $res = GameReportOperatingModel::where("id",$find['id'])->update($add);
                }else{
                    $res = GameReportOperatingModel::insertGetId($add);
                }

                if($res){
                    $rsps['code']=0;
                    $rsps['msg']='同步报表数据成功！';
                }else{
                    $rsps['code']=1;
                    $rsps['msg']='同步报表数据写入数据库失败,数据无变化！';
                }
            }

        }else{
            $rsps['code']=1;
            $rsps['msg']='同步报表数据失败！';
        }
        return $rsps;
    }

//    /**
//     * 获取新建的注单表名称
//     * @param string $type
//     * @return string
//     */
//    public function getZhuDanTableByDay($type = 'today') {
//        if ($type == 'today') {
//            $Ymd =Util::timeToStr(time(), 'Ymd');
//        } elseif ($type == 'yesterday') {
//            $Ymd = Util::timeToStr(strtotime("-1 day"), 'Ymd');
//        } else {
//            $Ymd = Util::timeToStr(Util::getMkTime($type), 'Ymd');
//        }
//        $full_table_name = "dq_game_zhudan_{$Ymd}";
//        self::createZhuDanDayTable($full_table_name);
//        return $full_table_name;
//    }
//
//    /**
//     * 创建当日注单统计表
//     */
//    public function createZhuDanDayTable($full_table_name) {
//        //创建当日变帐统计表
//        if (empty(Util::existTable($full_table_name))) {
//            $sql = <<<EOC
//                CREATE TABLE `{$full_table_name}` (
//              `id` bigint(20) NOT NULL AUTO_INCREMENT,
//              `zd_id` varchar(225) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '注单id',
//              `username` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '用户名',
//              `game_id` int(11) NOT NULL DEFAULT '0' COMMENT '游戏id',
//              `bets_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '投注金额',
//              `reward_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '收益金额',
//              `before_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '投注后余额',
//              `after_coin` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '结算后余额',
//              `add_time` bigint(20) NOT NULL DEFAULT '0' COMMENT '添加时间',
//              `begin_time` bigint(20) NOT NULL,
//              `end_time` bigint(20) NOT NULL,
//              `game_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '游戏名称',
//              `lebal` tinyint(1) NOT NULL DEFAULT '1' COMMENT '用户标签，1一般用户，2测试用户',
//              PRIMARY KEY (`id`,`zd_id`),
//              KEY `idx_username` (`username`) USING BTREE,
//              KEY `idx_begin_time` (`begin_time`) USING BTREE,
//              KEY `idx_end_time` (`end_time`) USING BTREE,
//              KEY `idx_add_time` (`add_time`) USING BTREE
//            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='注单记录表';
//EOC;
//            Db::execute($sql);
//        }
//    }

}