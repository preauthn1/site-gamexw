<?php

namespace app\common\FastGame\Socket;

use app\common\FastGame\Socket\R;
use app\common\FastGame\Base\Utils;
use think\facade\Db;

/* 20230731 增加在线xnguk功能
 CREATE TABLE `dq_user_online` (
`uo_uid` int(11) NOT NULL COMMENT '用户id',
  `uo_stime` int(11) NOT NULL DEFAULT '0' COMMENT '最近上线时间',
  `uo_etime` int(11) NOT NULL DEFAULT '0' COMMENT '最近下线时间',
  `uo_onstate` int(11) NOT NULL DEFAULT '0' COMMENT '在线状态 0下线 1在线 2疑似下线',
  `uo_ontime` int(11) NOT NULL DEFAULT '0' COMMENT '最近在线时长',
  `uo_nowpage` varchar(255) DEFAULT NULL COMMENT '最后所在页面',
  `uo_nowpagetime` int(11) DEFAULT '0' COMMENT '最后页面进入时间',
  `uo_nowgame` int(11) DEFAULT NULL COMMENT '最后进入游戏',
  `uo_nowgametime` int(11) DEFAULT '0' COMMENT '最后游戏进入时间',
  `uo_nowstate` int(11) DEFAULT '0' COMMENT '当前/最后所处项目 0未知 1页面 2游戏中',
  `uo_intime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `uo_uptime` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`uo_uid`) USING BTREE,
  KEY `uo_nowpage` (`uo_nowpage`),
  KEY `uo_nowgame` (`uo_nowgame`),
  KEY `uo_onstate` (`uo_onstate`),
  KEY `uo_nowstate` (`uo_nowstate`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='用户在线表';
CREATE TABLE `dq_user_online_temp` (
`uot_uid` int(11) NOT NULL COMMENT '用户id',
  `uot_nowpage` varchar(255) DEFAULT NULL COMMENT '所在页面，offline为特殊指令，表示下线',
  `uot_nowpagetime` int(11) NOT NULL DEFAULT '0' COMMENT '页面进入时间',
  `uot_nowgame` int(11) DEFAULT NULL COMMENT '进入游戏',
  `uot_nowgametime` int(11) NOT NULL DEFAULT '0' COMMENT '游戏进入时间',
  `uot_intime` int(11) NOT NULL DEFAULT '0' COMMENT '时间',
  PRIMARY KEY (`uot_uid`) USING BTREE
) ENGINE=MEMORY DEFAULT CHARSET=utf8 COMMENT='用户在线实时日志临时表';
---ini增加设置---
[onlinecount]
;是否开启统计 开启统计的情况下请同时执行command中关于socketPlugin统计在线的相关任务--通用设置
opencount=1
;统计间隔秒数--通用设置默认5
countstep=5
;中间结果缓存条数,根据在线用户数会占用redis的内存空间和读写io，不建议设置过大，此值的主要作用是保证短时阻塞时不会丢失用户在线数据,
;建议设置为消息服务器对接的最大项目用户数/5000*3 --通用设置 默认600：按100万用户计
cachedatanum=600
;数据库每轮处理在线消息条数，此设置为每个项目根据自身mysql服务器能力进行设置,默认50000,设置为5000的倍数为佳
procdatanums=100000
;疑似下线无动作分钟数,默认60
maybeofflinemin=60
*/

/**
 *
 */
class SockPlugin
{
    const MSGMODE_TOAST='toast';//提示
    const MSGMODE_POP='pop';//弹窗

    private static $modes=[
        self::MSGMODE_TOAST,
        self::MSGMODE_POP
    ];

    public static $inited=false;
    public static $dbidx=0;
    public static $resendlist=[1,5,10,10,15,30,60];

    public static function init(){
        if (!self::$inited) {
            self::$inited=true;
            self::$dbidx = Utils::ini('redis.dbidx', 3);
            self::$resendlist=Utils::ini('resendcfg.resendsteplist',self::$resendlist);
            self::$resendlist=array_unique(self::$resendlist);
        }
    }

    /**
     * 更新/注册用户消息服务器验证token
     * 此业务发生的情况分为：
     * 1.游客连接，即未登录的用户
     * 2.登录用户，当游客登录后应重新给予token
     * TODO 如果业务需要，可以将游客token和登录后的token对应起来，这样可以不需要在前端登录后重新连接消息服务器（达成此目标后，前端用户登录后不需要做任何操作，即可接收针对性信息）
     * @param $uid 用户uid
     * @param $token 用于登录消息服务器的TOken
     * @param $groups string | array 用户所属组，多个组使用数组或,隔开字符串
     * @return void
     * 对于未登录状态下的用户可以使用sessionid或其它唯一值来代替登录后的UID，此种情况下由于现有业务模式不支持对游客定向发送消息，因此这部分用户只能收到全局消息
     * TODO 将来如果需要对游客发送信息，可以在指定的唯一标识基础上进行业务扩展，但不需要动到本封装，比如维护一个游客ID列表，根据游客的行为进行一些相应的消息推送（例：一个游客在某个页面停留较长时间【可以在心跳中加入附加信息实现】后推送一条消息给当前游客）
     */
    public static function registerMsgUser($uid,$token,$groups=null){
        self::init();
        //TODO 考虑token的有效时间
        R::setex('msguser_'.$token, $uid,5, self::$dbidx);
        if ($groups !=null) self::groupAddUser($groups,$uid);
    }

    /**
     * 添加用户到组，不存在的组自动创建
     * @param string|array $groups
     * @param string|array $uids
     * @return void
     */
    public static function groupAddUser($groups,$uids){
        self::init();
        $groups=is_array($groups)?$groups:explode(',',$groups);
        $uids=is_array($uids)?$uids:explode(',',$uids);
        foreach ($groups as $group) {
            foreach ($uids as $uid) {
                R::hset('msgserver_group_'.$group,$uid,1,self::$dbidx);
            }
        }
    }

    /** 从组中移除用户
     * @param string|array $groups
     * @param string|array $uids
     * @return void
     */
    public static function groupRemoveUser($groups,$uids){
        self::init();
        $groups=is_array($groups)?$groups:explode(',',$groups);
        $uids=is_array($uids)?$uids:explode(',',$uids);
        foreach ($groups as $group) {
            foreach ($uids as $uid) {
                R::hdel('msgserver_group_'.$group,$uid,self::$dbidx);
            }
        }
    }

    /** 删除一个组
     * @param string|array $groups
     * @return void
     */
    public static function groupDelete($groups){
        self::init();
        $groups=is_array($groups)?$groups:explode(',',$groups);
        foreach ($groups as $group) {
            R::del('msgserver_group_'.$group,false,self::$dbidx);
        }
    }

    /** 写标准推送消息
     * @param $msg 推送内容
     * @param int|null $uid 推送用户UId null or 0 表示全局消息
     * @param string $mode 消息模式，const MSGMOD_XXX
     * @param int $showtime default 0 显示时长
     * @param string $title default '消息通知' 消息标题
     * @param int $resend default 0,消息重发时间（分钟），指定分钟内，消息未送达将按INI配置中的resendcfg.resendsteplist的设置时间进行重发(仅支持按人发送的消息)
     * @return string|true true表示写成功，否则为提示错误消息
     */
    public static function writeStandardMsg($msg,$uid=null,$mode='toast',$showtime=0,$title='消息通知',$resend=0){
        self::init();
//        if (in_array($mode,self::$modes)){
        $info=[
            'title'=>$title,
            'msg'=>$msg,
            'mode'=>$mode,
            'showtime'=>$showtime,
            'time'=>time()
        ];
        if ($uid){
            $info['uid']=$uid;
        }
        $flag=self::writeMsg($info,$resend);
        if ($flag){
            return true;
        }else{
            return 'write msg err';
        }
//        }else{
//            return 'msg mode err';
//        }
    }

    /** 写组或标签推送消息
     * @param $msg 推送内容
     * @param string|array|null $groups 推送组名 null 表示全局消息
     * @param string $mode 消息模式，const MSGMOD_XXX
     * @param int $showtime default 0 显示时长
     * @param string $title default '消息通知' 消息标题
     * @param int $resend default 0,消息重发时间（分钟），指定分钟内，消息未送达将按INI配置中的resendcfg.resendsteplist的设置时间进行重发(仅支持按人发送的消息)
     * @param int $type 0其他 1 线下充值 2线上充值  3提现 4实名认证
     * @return string|true true表示写成功，否则为提示错误消息
     */
    public static function writeGroupMsg($msg,$groups=null,$mode='toast',$showtime=0,$title='消息通知',$resend=0, $type = 0){
        self::init();
        if (in_array($mode,self::$modes)){
            $info=[
                'title'=>$title,
                'msg'=>$msg,
                'mode'=>$mode,
                'showtime'=>$showtime,
                'time'=>time(),
                'type' => $type
            ];
            if ($groups){
                if (!is_array($groups)) $groups=explode(',',$groups);
                $flag=true;
                foreach ($groups as $v){
                    if ($flag && !empty($v)){
                        $flag=self::writeMsg(array_merge($info,['group'=>$v]),$resend);
                    }
                    if (!$flag) break;
                }
            }else {
                $flag = self::writeMsg($info,$resend);
            }
            if ($flag){
                return true;
            }else{
                return 'write msg err';
            }
        }else{
            return 'msg mode err';
        }
    }

    /**
     * @param $msg
     * @param int $resend default 0,消息重发时间（分钟），指定分钟内，消息未送达将按INI配置中的resendcfg.resendsteplist的设置时间进行重发(仅支持按人发送的消息),此值会被$msg['resend']覆盖
     * @param int $resendtime 分钟数 default 0 消息下次发送时间，为0时表示没有下次重发了，否则进行重发处理，应用中调用时一般不需要设置此参数(但可以通过设置此参数实现延时发送的功能)，目前其仅应用于服务器处理重发消息
     * @return false|mixed
     */
    public static function writeMsg($msg,$resend=0,$resendtime=0){
        self::init();
//        Utils::L('onlinecount_test',[1, $msg, $resend, $resendtime]);
        $ex=$resendtime>0?$resendtime:'';
        if ($resendtime>0 && in_array($resendtime,self::$resendlist)){
            $ex=$resendtime;//时间队列后缀
            $msg['ntime']=time()+$resendtime*60;//下次发送时间
        }
//        Utils::L('onlinecount_test',[2, $msg, $resend, $resendtime, array_merge(['resend'=>$resend,'time'=>time()],$msg)]);
        //注意merge参数顺序不能更换，否则会导致重发消息时间不正确
        return R::rpush('msg_usermsgs'.$ex,http_build_query(array_merge(['resend'=>$resend,'time'=>time()],$msg), '', '&', PHP_QUERY_RFC3986),self::$dbidx);
    }

    /**
     * @param $all 是否获取消息服务器所有在线人数，true为获取全部人数 default false 为仅获取本项目下ini配置userprefix前缀部分的用户数量
     * @return int
     */
    public static function getSocketMsgServerOnlineUserNum($all=false){
        self::init();
        $userprefix=Utils::ini('userprefix','');
        $userprefix=empty($userprefix)?'default':$userprefix;
        $num=R::get('SocketServerOnlineUsers'.($all?'':('_'.$userprefix)),self::$dbidx);
        return $num??0;
    }

    /** 分钟任务，用于对在线用户统计缓存进行转移
     * @return void
     */
    public static function onlineUserInfoProcTask(){
        self::init();
        $userprefix=Utils::ini('userprefix','');
        $userprefix=empty($userprefix)?'default':$userprefix;
        $taskisrun=R::get('useronlinetask_'.$userprefix,self::$dbidx);
        if ($taskisrun) return;
        $num=Utils::ini('onlinecount.procdatanums',50000);
        $countstep=intval(Utils::ini('onlinecount.countstep',5));
        $taskrunfalgnum=$countstep<30?60:($countstep*2);
        $numcnt=0;
        $run=false;
        while(true){
            R::setex('useronlinetask_'.$userprefix,time(),$taskrunfalgnum,self::$dbidx);
            $rds=R::lpop('msgserver_onlinedata_' . $userprefix,self::$dbidx);

            if ($rds){
                if ($rds=='end'){
                    $run=true;
                }else {
                    try {
                        // --写入用户在线消息到临时表
                        $sql = "insert into dq_user_online_temp (uot_uid,uot_nowpage,uot_nowpagetime,uot_nowgame,uot_nowgametime,uot_intime) values " . $rds;
                        $rlt = Db::execute($sql);
                        //Utils::L('onlinecount', ['转存临时表数据', $sql, $rlt]);//TODO 生产环境删除
                        // --更新日期
                        $sql = " update dq_user_online_temp set uot_dt=FROM_UNIXTIME(uot_intime,'%Y%m%d')";
                        $rlt = Db::execute($sql);
                        //Utils::L('onlinecount', ['更新临时表日期', $sql, $rlt]);//TODO 生产环境删除
                    }catch (\Exception $ex){
                        Utils::L('onlinecount_err', ['=====转存临时表数据出错=====', $sql, $ex->getMessage(),'如果是出现的重复ID的请不用理会，如果此消息出现频繁，请联系开发人员']);
                    }
                    R::setex('useronlinetask_'.$userprefix,time(),$taskrunfalgnum,self::$dbidx);
                    $numcnt+=5000;
                    if ($numcnt>=$num){
                        $run=true;
                    }
                }
            }else{
                $run=true;
            }
            if ($run && $numcnt>0){
                $run=false;
                try {
                    Utils::L('onlinecount',['开始执行合并在线状态']);
                    //--合并用户在线消息到在线表
                    //新用户写入
                    $sql = "insert into dq_user_online (uo_dt,uo_uid,uo_intime)
select uot_dt,uot_uid,uot_intime from dq_user_online_temp
left join dq_user_online on uo_uid=uot_uid and uo_dt=uot_dt
where uo_uid is null group by uot_dt,uot_uid";
                    $rlt=Db::execute($sql);
                    Utils::L('onlinecount',['转存临时表数据',$sql,$rlt]);
                    R::setex('useronlinetask_'.$userprefix,time(),$taskrunfalgnum,self::$dbidx);
                    //更新在线状态信息
                    $sql = "update dq_user_online,dq_user_online_temp 
set uo_stime=if(uo_onstate=1,uo_stime,if(uot_nowpagetime>uot_nowgametime,uot_nowpagetime,uot_nowgametime)),
uo_etime=if(uot_nowpage='offline',uot_nowpagetime,uo_etime),
uo_onstate=if(uot_nowpage='offline',0,1),
uo_ontime=if(uot_nowpage='offline',uot_nowpagetime-if(uo_onstate=1,uo_stime,if(uot_nowpagetime>uot_nowgametime,uot_nowpagetime,uot_nowgametime)),uot_intime-if(uo_onstate=1,uo_stime,if(uot_nowpagetime>uot_nowgametime,uot_nowpagetime,uot_nowgametime))),
uo_nowpage=if(uot_nowpage='offline',uo_nowpage,ifnull(uot_nowpage,uo_nowpage)),
uo_nowpagetime=if(uot_nowpagetime=0,uo_nowpagetime,uot_nowpagetime),
uo_nowgame=ifnull(uot_nowgame,uo_nowgame),
uo_nowgametime=if(uot_nowgametime=0,uo_nowgametime,uot_nowgametime),
uo_nowstate=if(uot_nowpagetime>0,1,if(uot_nowgametime>0,2,0)),
uo_ontimecnt=uo_ontimecnt+if(uot_nowpage='offline',uot_nowpagetime-if(uo_onstate=1,uo_stime,if(uot_nowpagetime>uot_nowgametime,uot_nowpagetime,uot_nowgametime)),0),
uo_ontimecntreal=uo_ontimecntreal+if(uot_nowpage='offline',uot_nowpagetime-if(uo_onstate=1,uo_stime,if(uot_nowpagetime>uot_nowgametime,uot_nowpagetime,uot_nowgametime)),0),
uo_uptime=uot_intime
where uo_uid=uot_uid and uo_dt=uot_dt";
                    $rlt=Db::execute($sql);
                    Utils::L('onlinecount',['更新在线状态信息',$sql,$rlt]);
                    R::setex('useronlinetask_'.$userprefix,time(),$taskrunfalgnum,self::$dbidx);
                    //删除临时表
                    $sql = "truncate table dq_user_online_temp";
                    $rlt=Db::execute($sql);
                    Utils::L('onlinecount',['删除临时表',$sql,$rlt]);
                    //更新疑似下线状态
                    $sql = "update dq_user_online set 
                          uo_onstate=2,
                          uo_ontimecnt=uo_ontimecnt+uo_uptime-uo_stime
                      where uo_onstate=1 and uo_uptime<" . (time() - intval(Utils::ini('onlinecount.maybeofflinemin', 60)) * 60);
                    $rlt=Db::execute($sql);
                    Utils::L('onlinecount',['更新疑似下线状态',$sql,$rlt]);
                    R::setex('useronlinetask_'.$userprefix,time(),$taskrunfalgnum,self::$dbidx);
                }catch (\Exception $ex){
                    Utils::L('onlinecount_err',$ex->getMessage());
                }
                $numcnt=0;
            }
            sleep($countstep);
        }
    }
}