<?php
namespace app\common\FastGame\Socket;

use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Utils;
use app\common\FastGame\Socket\R;


class socketSrv
{

    private $host="127.0.0.1";
    private $nethost='127.0.0.1';
    private $port=12123;
    private $cfgname="server";//服务器配置段名称
    /** @var false|resource|\Socket|null  */
    private $linsterSrv=null;//主监听服务
    private $masterFlag=false;//是否主服务器
    private $sockets=[];//连接池
    private $users=[];//连接用户池
    private $userids=[];//用户id与连接池key关系表
    private $srvname=null;//服务器名称
    private $servers=[];//服务器池
    private $info=null;//当前服务器信息
    private $num=0;//当前服务器服务人数
    private $maxconn=10000;//每台服务器最多服务人数（消息服务器）
    private $srvhealth=0;//服务器健康检查时长
    private $timerhealth=0;//服务器信息更新计时器
    private $userhealth=10;//连接心跳检查
    private $timeruserhealth=0;//服务器用户健康检查计时器
    private $issuemsgnum=1000;//每轮处理消息的条数
    private $countOnlineStep=5;//统计在线用户间隔
    private $countOnlineSt=0;//统计在线用户计时器
    private $countOnline=0;//是否统计在线人数
    private $countOnlineCacheNum=600;//统计在线人数缓存最大条数

    private $resendsteplist=[1,1,1,1,1,5,5,5,5,5,10,10,10,10,10,15,15,15,15,15,30,30,30,30,30,60,60,60,60,60];//重发消息时间阶梯
    private $resendsteps=[];//$resendsteplist去重结果

    private $projectOnlineDatas=[];//项目在线用户记录信息信息
    private $projectOnlineDatasChange=[];//项目在线用户变更信息

    private $projectOnline=[];//项目在线用户统计

    private $dbidx=3;

    private $debug=0;
    private $fzlogwar=0;
    private $fzlogtime=0;
    private $testtimer=null;


    function log($msg,$force=false){
        if (!empty($this->debug) || $force) {
            if (is_array($msg)) {
                $msg['servername'] = $this->srvname;
            } else {
                $msg = ['servername' => $this->srvname, 'msg' => $msg];
            }
            Utils::L('socketLog',$msg);
        }
    }

    private function logerr($msg){

        Utils::L('socketErr',[$this->srvname,$msg]);
    }

    public function __construct($cfgname="server")
    {
        $this->timerhealth=time();
        $this->timeruserhealth=time();
        $this->dbidx=intval(Utils::ini('redis.dbidx',3));

        try {
            $this->srvhealth=Utils::ini('srvhealth',5);
            $this->userhealth=Utils::ini('usrhealth',10);
            $this->log(['服务器健康检查时长', $this->srvhealth],true);
            $this->debug=Utils::ini('debug',0);
            $this->cfgname = $cfgname;
            $this->log(['初始化服务', $cfgname],true);
            $this->issuemsgnum=Utils::ini($this->cfgname . '.issuemsgnum',$this->issuemsgnum);
            $this->maxconn=intval(Utils::ini($this->cfgname . '.maxconn',$this->maxconn));
            $this->countOnline=intval(Utils::ini('onlinecount.opencount',$this->countOnline));
            $this->countOnlineCacheNum=intval(Utils::ini( 'onlinecount.cachedatanum',$this->countOnlineCacheNum));
            $this->countOnlineStep=intval(Utils::ini('onlinecount.countstep',$this->countOnlineStep));
            $this->resendsteplist=Utils::ini('resendcfg.resendsteplist',$this->resendsteplist);
            if (is_string($this->resendsteplist)) $this->resendsteplist=explode(',',$this->resendsteplist);
            $this->resendsteps=array_unique($this->resendsteplist);
            $this->srvname=Utils::ini($this->cfgname . '.srvname','msgsrv_'.time().mt_rand(1000,9999));
            $this->log(['服务器名称', $this->srvname],true);
            $this->masterFlag = Utils::ini($this->cfgname . '.type', 'slv') == "mst";//默认是副服务器
            $this->log(['是否主服务器', $this->masterFlag],true);
            /*if ($this->masterFlag) $this->srvname="MsgServerMaster";//主服务器的固定名字*/
            $this->linsterSrv = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            $this->log(['创建监听服务', $this->linsterSrv,socket_strerror( socket_last_error())],true);
            if ($this->linsterSrv) {
                socket_set_option($this->linsterSrv, SOL_SOCKET, SO_REUSEADDR, 1);//1表示接受所有的数据包
                $this->log(['设置监听服务', $this->linsterSrv,socket_strerror( socket_last_error($this->linsterSrv))],true);
                $this->host = Utils::ini($this->cfgname . '.host', $this->host);
                $this->nethost = Utils::ini($this->cfgname . '.nethost', $this->nethost);
                $this->port = intval(Utils::ini($this->cfgname . '.port', $this->port));
                $this->log(['绑定服务', $this->host, $this->port],true);
                $flag = socket_bind($this->linsterSrv, $this->host, $this->port);
                if ($flag) {
                    $flag = socket_listen($this->linsterSrv);
                    if ($flag) {
                        $this->sockets[0] = $this->linsterSrv;
                        $this->log(['开启监听'],true);
                        if (!$this->masterFlag){
                            //注册服务器
                            $this->registerMine();
                        }
                        //注册服务器
                        $this->run();
                    } else {
                        $this->log(['err', '开启监听出错！'],true);
                    }
                } else {
                    $this->log(['err', '绑定服务出错！'],true);
                }
            } else {
                $this->log(['err', '创建监听服务出错！'],true);
            }
        }catch (\Exception $ex){
            $this->log(['errex',$ex->getMessage(),$ex->getTraceAsString()],true);
        }
    }

    function registerMine($addnum=0){
        if ($this->masterFlag) return;
        $info=$this->info;
        if ($this->info==null){
            $info=[
                'srvname'=>$this->srvname,
                'nethost'=>$this->nethost,
                'host'=>$this->host,
                'port'=>$this->port,
                'start'=>$this->info==null?date("Y-m-d H:i:s"):$this,
                'health'=>time(),
                'maxconn'=>$this->maxconn,
                'num'=>$this->num,
                'projectnum'=>[]
            ];
        }else{
            $this->num+=$addnum;
            $info['health']=time();
            $info['num']=$this->num;
            $info['projectnum']=$this->projectOnline;
        }
        $this->info=$info;
        if (time()-$this->timerhealth>=$this->srvhealth-1){
            $this->timerhealth=time();
            R::rpush('msgServersList',json_encode($this->info),$this->dbidx);
            $this->log(['汇报服务器状态',$this->info]);
        }
    }

    function testsendmsg(){
        return;
        if ($this->testtimer==null) $this->testtimer=time();
        if (time()-$this->testtimer>5) {
            foreach ($this->sockets as $key => $sock) {
                if ($key != 0) {
                    socket_write($sock, $this->code(time()));
                }
            }
            $this->testtimer=time();
        }
    }

    private function __procUserMsg($msg){
        $srv=R::get('msguser_server_'.$msg['uid'],$this->dbidx);
//        Utils::L('onlinecount_test',[5, $srv]);
        if ($srv){
            $msg=http_build_query($msg, '', '&', PHP_QUERY_RFC3986);
            R::rpush('msgserver_msglist_'.$srv,$msg,$this->dbidx);
        }else{
            //如果用户不在线
            $this->__resendUserMsg($msg);
        }
    }

    private function __procGlobalMsg($msg){
        $msg=http_build_query($msg, '', '&', PHP_QUERY_RFC3986);
        foreach ($this->servers as $k=>$v){
            R::rpush('msgserver_msggloballist_'.$k,$msg,$this->dbidx);
        }
    }

    private function __procGroupMsg($msg){
        $this->__procGlobalMsg($msg);
    }

    /** 主服务器分发消息队列
     * @return void
     */
    private function issueMsgList(){
        for($i=0;$i<$this->issuemsgnum;$i++){
            $msg=R::lpop('msg_usermsgs',$this->dbidx);
            if ($msg){
                $this->log(['拿到待分发消息',$msg]);
                try {
                    $msgo =[];
                    parse_str($msg, $msgo);
                    $msgo['order']='msg';
                    $this->log(['准备处理分发消息',$msgo]);
                    //TODO 处理分组消息
                    if (isset($msgo['uid'])){
                        $this->__procUserMsg($msgo);
                    }else{
                        if (isset($msgo['group'])) {
                            $this->__procGroupMsg($msgo);
                        }else {
                            $this->__procGlobalMsg($msgo);
                        }
                    }
                }catch (\Exception $ex){
                    $this->logerr(['处理消息队列出错',$ex->getMessage()]);
                }
            }else{
                break;
            }
        }
        //分发重发消息
        $isnum=ceil($this->issuemsgnum/count($this->resendsteps));//重发消息每个列表处理数量
        $st=time();
        foreach ($this->resendsteps as $v){
            $num=0;
            while ($num<$isnum){
                $num++;
                $msg=R::lpop('msg_usermsgs'.$v,$this->dbidx);
                if ($msg) {
                    try {
                        $msgo = [];
                        parse_str($msg, $msgo);
                        $msgo['order'] = 'msg';
                        $this->log(['准备处理分发消息', $msgo]);
                        if ($msgo['ntime']<=$st) {//到了应发送时间
                            if (isset($msgo['uid'])) {
                                $this->__procUserMsg($msgo);
                            }
                        }else{//未到时间的，头部写回
                            R::lpush('msg_usermsgs'.$v,$msg,$this->dbidx);
                            break;
                        }
                    } catch (\Exception $ex) {
                        $this->logerr(['处理重发消息队列出错', $ex->getMessage()]);
                    }
                }else{
                    break;
                }
            }
        }
    }

    private function __procUserGlobalMsg(){
        $flag=true;
        $msg=R::lpop('msgserver_msggloballist_'.$this->srvname,$this->dbidx);
        if ($msg){
            $flag=false;
            $msgo =[];
            parse_str($msg, $msgo);
            $group = $msgo['group'] ?? null;
            foreach ($this->users as $k=>$v){
//                $this->log(["group" => $group, 'user' => $k, 'user_v' => $v], true);
                if ($group==null || isset($v['uid']) && R::hget('msgserver_group_'.$group,$v['uid'],$this->dbidx)) {
                    $this->send($msg, $k);
                }
            }
        }
        return $flag;
    }

    private function __procUserUserMsg(){
        for($i=0;$i<$this->issuemsgnum;$i++){
            $msg=R::lpop('msgserver_msglist_'.$this->srvname,$this->dbidx);
//            Utils::L('onlinecount_test',[6, $msg]);
            if ($msg){
                $this->log(['拿到用户消息',$msg]);
                $info=[];
                parse_str($msg,$info);
                $this->log(['处理用户消息',$msg,$this->userids]);
                if(isset($info['uid'])){
                    $key=$info['uid'];
                    $rlt=false;
//                    Utils::L('onlinecount_test',[7, $info]);
                    if (isset($this->userids[$key])){
//                        Utils::L('onlinecount_test',[9, $this->userids[$key], $key, $this->userids]);
                        $rlt=$this->send($msg,$this->userids[$key]);
                    }
//                    Utils::L('onlinecount_test',[8, $rlt]);
                    if ($rlt===false){
                        //消息发送失败，回写消息
                        $this->__resendUserMsg($info);
                    }
                }
            }else{
                break;
            }
        }
    }

    /** 发送失败的消息，如果有设置重发的，则重新发送
     * @param $msg
     * @return void
     */
    private function __resendUserMsg($msg){
//        Utils::L('onlinecount_test',[3, $msg]);
        if (!isset($msg['resend'])) return;//不需要重发的消息
        if ($msg['resend']==0) return;//不需要重发的消息
        $resendnum=isset($msg['resendnum'])?$msg['resendnum']:0;//已重发次数
        $time=$msg['time'];
        $st=time();
        if ($resendnum+1>count($this->resendsteplist) || $time+$msg['resend']*60<=$st) return;//已经达到最大发送次数或超时
        $resendnum++;
        $msg['resendnum']=$resendnum;
//        Utils::L('onlinecount_test',[4, $msg]);
        SockPlugin::writeMsg($msg,0, $this->resendsteplist[$resendnum-1]);//回写消息

    }

    /** 副服务器推送消息
     * @return void
     */
    private function procMsgList(){
        //有全局消息则只处理一条
        try {
            if ($this->__procUserGlobalMsg()) $this->__procUserUserMsg();
        }catch (\Exception $ex){
            $this->logerr(['处理消息队列出错',$ex->getMessage()]);
        }
    }

    function run(){
        $timer=time();
        while(true){
            try {
                $this->testsendmsg();
                $this->registerMine();//服务器健康心跳
                if ($this->masterFlag) {
                    //主服务器更新可用服务器
                    $this->getCanUseServer(false);
                    //分发消息
                    $this->issueMsgList();
                } else {
                    //处理发送消息
                    $this->procMsgList();
                    //清除离线用户
                    $this->fresh();
                    //执行统计
                    $this->onLineCount();
                }

                //服务
                //接受连接
                $changes = $this->sockets;
                $write = null;
                $except = null;
                socket_select($changes, $write, $except, 0);
                foreach ($changes as $idx => $sock) {
                    if ($idx == 0) {
                        //接受一个连接
                        $client = socket_accept($this->linsterSrv);
                        $this->log(['接受一个连接', $client]);
                        $key = uniqid("", true);
                        $key = str_replace(".", "_", $key);
                        $this->log('加入: ' . $key);
                        $this->sockets[$key] = $client;//存入连接池
                        $this->users[$key] = array(
                            'uid' => null,//用户标识
                            'socket' => $client,  //记录新连接进来client的socket信息
                            'shou' => false,       //标志该socket资源没有完成握手
                            'pid' => null,//项目标识，从UID中取前缀
                            'realuid' => null,//去掉项目标识后的UID
                            'token' => null,
                            'vflag' => false,//是否通过了验证
                            'time' => time(), //标识用户超时时间，如果多长时间没有验证通过就关闭
                            'ltime' => time(),//标识用户超时时间，如果多长时间没有交互就关闭，防止内存占用
                        );
                        //$this->log($this->users);
                    } else {
                        $k = $idx;
                        $this->log('活动来了: ' . $k);
                        $len = 0;
                        $buffer = '';
                        //读取socket信息，注意：第二个参数是引用传参，即接收数据，第三个参数是接收数据的长度
                        do {
                            $l = socket_recv($sock, $buf, 1000, 0x8);
                            $len += $l;
                            $buffer .= $buf;
                        } while ($l == 1000 && $len < 1000);
                        //$this->debug && Utils::L($this->logfile,"读取信息: $k ---- $len:".$buffer);
                        if ($len >= 1000) {
                            $this->log('消息过长，关闭连接: ' . $k);
                            $this->close($k);
                        }
                        if ($len < 7) {
                            $this->log('消息过短，关闭连接: ' . $k . '-----' . $buffer);
                            $this->close($k);
                            continue;
                        }
                        if ($len > 0) {
                            if (!isset($this->users[$k])) continue;//TODO 这里会偶尔出现undefined index的错误
                            if (!$this->users[$k]['shou']) {
                                //握手
                                $this->log('正在握手: ' . $k);
                                $this->woshou($k, $buffer);
                            } else {
                                //除了握手就只处理验证，只要不是验证就关闭
                                try {
                                    $buffer = $this->uncode($buffer, $k);
                                } catch (\Exception $ex) {
                                    $buffer = false;
                                }
                                if ($buffer == false) {
                                    $this->log('非法信息: ' . $k);
                                    $this->close($k);
                                } else {
                                    $this->procUserMsg($k, $buffer);
                                    //$this->log(['收到消息',$k,$buffer]);
                                }
                            }
                        }
                    }
                }
            }catch (\Exception $ex){
                $this->logerr($ex->getTraceAsString());
            }
        }
    }

    //处理用户加入
    function procUserMsg($k,$msg){
        $this->log("procUserMsg:$k:$msg");
        if (isset($this->users[$k])) $this->users[$k]["ltime"]=time();//超时关掉连接
        //将查询字符串解析到第二个参数变量中，以数组的形式保存如：parse_str("name=Bill&age=60",$arr)
        parse_str($msg,$g);
        $this->log($g);
        //可扩展指令
        if (!isset($g['type']) || !in_array($g["type"],["add",'askserver','online']) || $g['type']=='add' && (!isset($g["token"]) || empty($g["uid"]) || empty($g["token"]))){
            //非法，关掉
            $this->log("非法信息");
            $this->log($g);
            $this->close($k);
            return;
        }
        //验证用户
        //$this->debug && Utils::L($this->logfile,"用户消息");
        //$this->debug && Utils::L($this->logfile,$g);
        //$this->debug && Utils::L($this->logfile,$msg);


        switch ($g["type"]) {//TODO 可扩展指令
            case "add": //用户进入
                if (!$this->masterFlag) {
                    if ($this->checkUserLoginState($g["uid"],$g["token"])){
                        $tuid=$g["uid"];
                        $this->users[$k]["uid"] = $tuid;
                        $pren=strpos($tuid,'_');
                        if ($pren===false || $pren==0){
                            $pre='default';
                            $ruid=$tuid;
                        }else{
                            $pre=substr($tuid,0,$pren);
                            $ruid=substr($tuid,$pren+1);
                        }
                        $this->users[$k]['pid']=$pre;//项目标识
                        $this->users[$k]['realuid']=$ruid;//项目中实际uid
                        $this->users[$k]["vflag"] = true;
                        $this->log("验证成功");
                        $this->log($this->users);
                        $this->users[$k]["ltime"]=time();//超时关掉连接
                        $this->userids[$tuid]=$k;//记录用户Id与key关系
                        R::set('msguser_server_'.$g["uid"],$this->srvname,$this->dbidx);//更新用户所在服务器
                        $this->registerMine(1);
                        $this->send("order=login",$k);
                    }else{
                        $this->log("验证UID和TOKEN失败，关闭：$k", true);
                        $this->log($g, true);
                        $this->close($k);
                    }
                    //验证成功后就从socket里删除，不再监视他的数据
                    //unset($this->sockets[$k]);
                }else{
                    $this->close($k);
                }
                break;
            case 'askserver'://询问主服务可连接服务
                if ($this->masterFlag){
                    $srv=$this->getCanUseServer();
                    $this->send($srv,$k);
                    $this->close($k);
                }
                break;
            case 'online'://保持在线
                if (!$this->masterFlag){
                    if (isset($this->users[$k])) $this->users[$k]["ltime"]=time();//超时关掉连接
                    if(isset($g['op'])){
                        $this->procOnlineInfo($k,$g);
                    }else {
                        $this->send("order=live", $k);
                    }
                }else{
                    $this->close($k);
                }
                break;
        }

        if( $this->users ){
            R::set('onlineUserList', json_encode([
                'user' => $this->users,
                'user_ids' => $this->userids
            ]), $this->dbidx);
        }
        //验证成功后删除socket

    }

    /** 处理用户发来的在线信息
     * @param $k
     * @param $g
     * @return void
     */
    function procOnlineInfo($k,$g){
        //$this->log(['处理用户在线信息',$g],true);
        if (isset($this->users[$k])) {
            switch ($g['op']) {
                case 'page'://浏览页面
                    $page = $g['page'];
                    $this->pushOnlineInfo($k,time(),$page);
                    break;
                case 'playgame'://玩游戏
                    $gameid = $g['gameid'];
                    $this->pushOnlineInfo($k,time(),null,$gameid);
                    break;
            }
        }
    }

    /** 验证用户是否登录 ，默认为redis中读取 user_token=uid，否则要在继承中重写此文宗
     * @param $uid
     * @param $token
     * @return bool
     * @author twt326
     * @date 2022-07-08 19:58
     */
    protected function checkUserLoginState($uid,$token){
//        return true;//TODO 正式版删除此行，建议继承重写
        $rlt=R::get('msguser_'.$token,$this->dbidx);
        R::del('msguser_'.$token,$this->dbidx);
        return $rlt==$uid;
    }

    //$k 发信息人的socketID $key接受人的 socketID ，根据这个socketID可以查找相应的client进行消息推送，即指定client进行发送
    function send($ar,$key){
        //TODO 如果有超长数据要发送的情况下，这里要做分段发送处理
        /*$st="Message to sent";
        $length = strlen($st);

        while (true) {

            $sent = socket_write($socket, $st, $length);

            if ($sent === false) {

                break;
            }

            // Check if the entire message has been sented
            if ($sent < $length) {

                // If not sent the entire message.
                // Get the part of the message that has not yet been sented as message
                $st = substr($st, $sent);

                // Get the length of the not sented part
                $length -= $sent;

            } else {

                break;
            }

        }*/
        //对发送信息进行编码处理
        $str = $this->code(is_string($ar)?$ar:json_encode($ar));
        if (isset($this->users[$key])) {
            $this->users[$key]["ltime"] = time();//超时关掉连接
            if (isset($this->users[$key]['socket'])) {
                $flag=socket_write($this->users[$key]['socket'], $str, strlen($str));
                if ($flag===false){
                    //发送失败
                    $this->close($key);
                }else{
                    return true;
                }
            }
        }
        return false;
        //$this->log("发送消息：$key"." MSG:".$ar );

    }

    /** 写用户在线消息
     * @param $socketkey
     * @param $st
     * @param $page
     * @param $gameid
     * @return void
     */
    protected function pushOnlineInfo($socketkey,$st,$page=null,$gameid=null){
        //$this->log(['单条处理',$socketkey,$st,$page,$gameid],true);
        if ($page==null && $gameid==null) return;
        if (isset($this->users[$socketkey])) {
            //$this->log(['用户存在',$this->users[$socketkey]],true);
            if (!$this->users[$socketkey]['uid']) return;
            $project=$this->users[$socketkey]['pid'];
            $uid=$this->users[$socketkey]['realuid'];
            if (!is_numeric($uid)) return;//排除特殊UID不统计在线信息，比如后台管理会加前缀manager_会导致写在线表出错（在线表UID是整型的）
            $suid=$this->users[$socketkey]['uid'];
            $sts = (ceil($st / $this->countOnlineStep) * $this->countOnlineStep) . '';
            if (!isset($this->projectOnlineDatasChange[$project])) {
                $this->projectOnlineDatasChange[$project] = [];
            }
            //$this->log(['当前缓存',$project,$sts,$this->projectOnlineDatas],true);
            $change = null;
            if (isset($this->projectOnlineDatas[$suid])) {
                if ($page != null && $this->projectOnlineDatas[$suid]['p'] != $page || $gameid != null && $this->projectOnlineDatas[$suid]['g'] != $gameid) {
                    $change = [
                        'u' => $uid,
                        'sp' => ($page != null && $this->projectOnlineDatas[$suid]['p'] != $page) ? $st : $this->projectOnlineDatas[$suid]['sp'],
                        'p' => ($page != null && $this->projectOnlineDatas[$suid]['p'] != $page) ? $page : $this->projectOnlineDatas[$suid]['p'],
                        'sg' => ($gameid != null && $this->projectOnlineDatas[$suid]['g'] != $gameid) ? $st : $this->projectOnlineDatas[$suid]['sg'],
                        'g' => ($gameid != null && $this->projectOnlineDatas[$suid]['g'] != $gameid) ? $gameid : $this->projectOnlineDatas[$suid]['g']
                    ];
                }
            } else {
                $change = [
                    'u' => $uid,
                    'sp' => $page == null ? 0 : $st,
                    'p' => $page,
                    'sg' => $gameid == null ? 0 : $st,
                    'g' => $gameid
                ];


            }
            if ($change != null) {
                $this->projectOnlineDatas[$suid] = $change;
                if (!isset($this->projectOnlineDatasChange[$project][$sts])) {
                    $this->projectOnlineDatasChange[$project][$sts] = [];
                }
                $this->projectOnlineDatasChange[$project][$sts][$uid]=$change;
                //$this->log(['单条处理结果',$change,$this->projectOnlineDatasChange[$project],$this->projectOnlineDatas],true);
            }
            if ($page=='offline'){
                unset($this->projectOnlineDatas[$suid]);
            }
        }
    }

    protected function onLineCount(){
        $st=time();

        if ($this->masterFlag || $this->countOnline==0) return;

        if ($st-$this->countOnlineSt>$this->countOnlineStep){
            //$this->log(['准备提交REDIS',$this->masterFlag,$this->countOnline,$this->projectOnlineDatasChange,$st,$this->countOnlineSt,$this->countOnlineStep],true);
            $datas=[];
            foreach ($this->projectOnlineDatasChange as $pk=>$pv) {
                $datas=[
                    'cnt'=>0
                ];
                //清除超过设定的缓存
                $cachelen = R::llen('msgserver_onlinedata_' . $pk, $this->dbidx);
                if ($cachelen>$this->countOnlineCacheNum){
                    R::ltrim('msgserver_onlinedata_' . $pk,$this->countOnlineCacheNum*-1,-1,$this->dbidx);
                }
                foreach ($pv as $sk=>$sv){
                    //$this->log(['准备提交处理',$pk,$sk,$sv,$st],true);
                    if (intval($sk)<$st) {
                        //写更新缓存
                        foreach ($sv as $ik=>$iv) {
                            array_push($datas,"(".$iv['u'].",".(empty($iv['p'])?'null':("'".$iv['p']."'")).",".$iv['sp'].",".(empty($iv['g'])?'null':$iv['g']).",".$iv['sg'].",".$st.")");
                            $datas['cnt']++;
                            if ($datas['cnt']>=5000){
                                unset($datas['cnt']);
                                R::rpush('msgserver_onlinedata_' . $pk,implode(',',$datas),$this->dbidx);
                                //$this->log(['提交REDIS',$pk,$datas],true);
                                $datas=[
                                    'cnt'=>0
                                ];
                            }
                        }
                        unset($this->projectOnlineDatasChange[$pk][$sk]);
                        if ($datas['cnt']>0){
                            unset($datas['cnt']);
                            R::rpush('msgserver_onlinedata_' . $pk,implode(',',$datas),$this->dbidx);
                            //$this->log(['提交REDIS1',$pk,$datas],true);
                            $datas=[
                                'cnt'=>0
                            ];
                        }
                        R::rpush('msgserver_onlinedata_' . $pk,'end',$this->dbidx);
                    }

                }
            }
            $this->countOnlineSt = $st;
        }
    }

    /**
     * 获取可提供服务的服务器
     * @return array|bool
     * @author twt326
     * @date 2022-07-08 15:10
     */
    protected function getCanUseServer($get=true){
        if (!$this->masterFlag) return;
        $st=time();
        if ($st-$this->timerhealth>$this->srvhealth){
            //删除可能不健康的服务器
            foreach ($this->servers as $key=>$v){
                if ($st-$v['health']>$this->srvhealth){
                    unset($this->servers[$key]);
                }
            }
            //加入新的服务器
            while($srv=R::lpop('msgServersList',$this->dbidx)){
                $srv=json_decode($srv,true);
                $this->servers[$srv['srvname']]=$srv;
            }
            $this->timerhealth=time();
            $logstr="";
            foreach ($this->servers as $v) {
                foreach ($v as $k=>$item) {
                    if (is_array($item)){
                        $logstr.=$k.":[";
                        foreach ($item as $ik=>$iv){
                            $logstr.=$ik.":".$iv." ";
                        }
                        $logstr.="] ";
                    }else{
                        $logstr.=$k.":".$item." ";
                    }

                }
                $logstr.="
";
            }
            $this->log($logstr,false);
            //统计当前在线人数，因是根据消息服务器连接数来确定在线人数的，所以在消息服务器工作不正常或前端连接不正常的情况下统计结果会有误差
            //另：在没有加同一用户有登录挤掉前次登录或不允许后次登录的时候当前端同一用户多次连接的时候会导致统计人数多出
            $SocketServerOnlineUsers=0;
            $SocketServerOnlineUsersByProject=[];
            foreach ($this->servers as $k => $v) {
                $SocketServerOnlineUsers+=$v['num'];
                if (isset($v['projectnum'])){
                    foreach ($v['projectnum'] as $proj=>$pnum){
                        if (isset($SocketServerOnlineUsersByProject[$proj])){
                            $SocketServerOnlineUsersByProject[$proj]+=$pnum;
                        }else{
                            $SocketServerOnlineUsersByProject[$proj]=$pnum;
                        }
                    }
                }
            }
            //TODO 这里是直接写到消息服务器redis，生产环境中使用时要使用框架的redis操作类写到env配置中的redis，或者后台读取的时候直接读消息服务器redis也可以
            R::set('SocketServerOnlineUsers',$SocketServerOnlineUsers,$this->dbidx);
            foreach ($SocketServerOnlineUsersByProject as $k=>$v){
                R::set('SocketServerOnlineUsers_'.$k,$v,$this->dbidx);
            }
        }
        if ($get) {
            //分配服务器
            $srv = null;
            $num = -1;
            foreach ($this->servers as $k => $v) {
                if ($v['num']<$v['maxconn']) {
                    if ($num == -1 || $num > $v['num']) {
                        $srv = $v;
                        $num = $v['num'];
                    }
                }
            }
            if ($srv==null) {
                return 'order=noservers';
            } else {

                return 'order=server&host=' . $srv['nethost'] . '&port=' . $srv['port'];
            }
        }
    }

    /**
     * 清理服务
     * @author twt326
     * @date 2022-07-07 20:48
     */
    protected function fresh(){
        $st=time();
        if ($st-$this->timeruserhealth>$this->userhealth) {
            $this->log('FRESH---------------------');
            try {
                //超时未验证成功的关闭 验证是从redis中拿他的信息（uid+token），拿得到就验证通过
                foreach ($this->sockets as $idx => $sock) {
                    if ($idx != 0 && isset($this->users[$idx])) {
                        if ((!$this->users[$idx]["shou"] || !$this->users[$idx]["vflag"]) && $st- $this->users[$idx]["time"] >= 2) $this->close($idx);
                    }
                }
                //清除超时的用户，以免占用内存空间
                $connnum=0;//统计消息服务器总在线人数
                $projconnnum=[];//统计消息服务器各项目在线人数
                foreach ($this->users as $k => $v) {
                    if ($st - $v["ltime"] > $this->userhealth){
                        $this->close($k);
                    } else{
                        $connnum++;
                        if (isset($projconnnum[$v['pid']])){
                            $projconnnum[$v['pid']]++;
                        }else{
                            $projconnnum[$v['pid']]=1;
                        }
                    }
                }
                $this->projectOnline=$projconnnum;
                $this->num=$connnum;//count($this->sockets);
                $war=$this->num/$this->maxconn;
                $warmsg=null;
                if ($war>0.99){
                    $warmsg='Err：服务器服务能力已满['.$war.']['.$this->num.'/'.$this->maxconn.']！';
                }else if ($war>0.85){
                    $warmsg='Warning：服务器高负载['.$war.']['.$this->num.'/'.$this->maxconn.']！';
                }else if ($war>0.7){
                    $warmsg='Notice：服务器繁忙['.$war.']['.$this->num.'/'.$this->maxconn.']！';
                }else{
                    $this->fzlogwar=$war;
                }
                if ($warmsg){
                    //负载持续增长每60秒输出一次日志
                    if ($this->fzlogwar<$war && $st-$this->fzlogtime>60) {
                        $this->logerr($warmsg);

                        $this->fzlogwar=$war;
                        $this->fzlogtime=$st;
                    }else{
                        if($this->fzlogwar>$war) $this->fzlogwar=$war;
                    }

                }

            } catch (\Exception $ex) {
                $this->log('FRESH ERR: ' . $ex->getMessage());
            }
            $this->timeruserhealth=time();
        }
    }

    /*
   * 函数说明：对client的请求进行回应，即握手操作
   * @$k clien的socket对应的健，即每个用户有唯一$k并对应socket
   * @$buffer 接收client请求的所有信息
   */
    protected function woshou($k,$buffer){
        //截取Sec-WebSocket-Key的值并加密，其中$key后面的一部分258EAFA5-E914-47DA-95CA-C5AB0DC85B11字符串应该是固定的
        $buf  = substr($buffer,strpos($buffer,'Sec-WebSocket-Key:')+18);
        $key  = trim(substr($buf,0,strpos($buf,"\r\n")));
        $new_key = base64_encode(sha1($key."258EAFA5-E914-47DA-95CA-C5AB0DC85B11",true));

        //按照协议组合信息进行返回
        $new_message = "HTTP/1.1 101 Switching Protocols\r\n";
        $new_message .= "Upgrade: websocket\r\n";
        $new_message .= "Sec-WebSocket-Version: 13\r\n";
        $new_message .= "Connection: Upgrade\r\n";
        $new_message .= "Sec-WebSocket-Accept: " . $new_key . "\r\n\r\n";
        socket_write($this->users[$k]['socket'],$new_message,strlen($new_message));

        //对已经握手的client做标志
        $this->users[$k]['shou']=true;
        $this->log("握手后");
        $this->log($this->users );
        //握手成功从socket里删除，以免影响性能 还要等验证，所以不能现在删除，应该是等超时或验证成功再删除
        //unset($this->sockets[$k]);
        return true;

    }

    /**
     * 关闭指定的连接
     * @param $k
     * @author twt326
     * @date 2022-07-07 20:40
     */
    protected function close($k){
        try {
            $this->log("key:$k close:");
            $this->log($this->users[$k]);
            if (!$this->masterFlag && $this->users[$k]['uid']) $this->pushOnlineInfo($k, time(), 'offline');
            //断开相应socket
            if ($this->users[$k]['socket']) socket_close($this->users[$k]['socket']);
            $this->registerMine($this->users[$k]['vflag'] ? -1 : 0);
            //删除用户ID与key的关系
            if (!empty($this->users[$k]['uid'])) {
                unset($this->userids[$this->users[$k]['uid']]);
                //删除用户服务器标识
                R::del('msguser_server_'.$this->users[$k]['uid'],$this->dbidx);
            }
            //删除相应的user信息
            unset($this->users[$k]);
            //重新定义sockets连接池
            unset($this->sockets[$k]);
        }catch(\Exception $ex){
            $this->logerr( $ex->getMessage());
        }

    }

    //解码函数
    function uncode($str,$key){
        $len=0;
        $mask=[];
        $n=0;
        $s=0;
        $mask = array();
        $data = '';
        $msg = unpack('H*',$str);
        //$this->debug && Utils::L($this->logfile,'unpack: ');
        //$this->debug && Utils::L($this->logfile,$msg);
        $head = substr($msg[1],0,2);
        if ($head == '81' && !isset($this->slen[$key])) {
            $len=substr($msg[1],2,2);
            $len=hexdec($len);//把十六进制的转换为十进制
            if(substr($msg[1],2,2)=='fe'){
                $len=substr($msg[1],4,4);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],4);
            }else if(substr($msg[1],2,2)=='ff'){
                $len=substr($msg[1],4,16);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],16);
            }
            $mask[] = hexdec(substr($msg[1],4,2));
            $mask[] = hexdec(substr($msg[1],6,2));
            $mask[] = hexdec(substr($msg[1],8,2));
            $mask[] = hexdec(substr($msg[1],10,2));
            $s = 12;
            $n=0;
        }else if(isset($this->slen[$key]) && $this->slen[$key] > 0){
            $len=$this->slen[$key];
            $mask=$this->ar[$key];
            $n=$this->n[$key];
            $s = 0;
        }
        /*$this->debug && Utils::L($this->logfile,[
            'len'=>$len,
            'mask'=>$mask,
            's'=>$s,
            'n'=>$n
        ]);*/
        $e = strlen($msg[1])-2;
        for ($i=$s; $i<= $e; $i+= 2) {
            $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
            $n++;
        }
        //$this->debug && Utils::L($this->logfile,$data);
        $dlen=strlen($data);

        if($len > 255 && $len > $dlen+intval($this->sjen[$key])){
            $this->ar[$key]=$mask;
            $this->slen[$key]=$len;
            $this->sjen[$key]=$dlen+intval($this->sjen[$key]);
            $this->sda[$key]=$this->sda[$key].$data;
            $this->n[$key]=$n;
            return false;
        }else{
            unset($this->ar[$key],$this->slen[$key],$this->sjen[$key],$this->n[$key]);
            $data=(isset($this->sda[$key])?$this->sda[$key]:"").$data;
            unset($this->sda[$key]);
            return $data;
        }

    }

    //与uncode相对
    function code($msg){
        $frame = array();
        $frame[0] = '81';
        $len = strlen($msg);
        if($len < 126){
            $frame[1] = $len<16?'0'.dechex($len):dechex($len);
        }else if($len < 65025){
            $s=dechex($len);
            $frame[1]='7e'.str_repeat('0',4-strlen($s)).$s;
        }else{
            $s=dechex($len);
            $frame[1]='7f'.str_repeat('0',16-strlen($s)).$s;
        }
        $frame[2] = $this->ord_hex($msg);
        $data = implode('',$frame);
        return pack("H*", $data);
    }

    function ord_hex($data)  {
        $msg = '';
        $l = strlen($data);
        $data=str_split($data);
        //$data=explode('',$data);
        for ($i= 0; $i<$l; $i++) {
            $msg .= dechex(ord($data[$i]));
        }
        return $msg;
    }
}
