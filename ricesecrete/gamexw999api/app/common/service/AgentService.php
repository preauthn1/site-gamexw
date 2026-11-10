<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\model\UserAgentConfigModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use think\facade\Db;

class AgentService {

    public static function getFySum($field, $where = null) {
        if (!in_array($field, ['sff_fy', 'youhui', 'sff_ks'])) {
            return 0;
        }
        if ($field == 'youhui') {
            $field = Db::raw("sff_payinfee+sff_payoutfee+sff_yh+sff_back+sff_tec");
        }
        return Db::name("third_stats_fyfy")->where($where)->where('sff_fy', '>', 0)->sum($field);
    }

    public static function getBetsSum($field, $where = null) {
        if (!in_array($field, ['su_bet'])) {
            return 0;
        }
        $table = \app\common\service\BetService::getThirdStatsTable();
        return Db::name($table['_stats_global'])->where($where)->sum('su_bet');
    }
    public static function getBetsUsers($where = null) {
        $table = \app\common\service\BetService::getThirdStatsTable();
        return Db::name($table['_stats_global'])->where($where)->count('DISTINCT su_uid');
    }
    /**
     * 获取符合领取邀请奖励要求的推广人数（弃用）
     * @param $uid
     * @return int
     */
    public static function getAgentSellUsers($uid) {
        $ApiRedis=new ApiRedis();
        $sys=$ApiRedis->systemInfo();
        if($sys['tg_condition'] > 0){
            $recU = UserOfflineModel::field('uid,sum(arrival_price) as sum')->whereIn("uid", function ($query) use($uid) {
                $query->name("user")->where("rec_id", $uid)->field('id');
            })->whereIn("status", [3,4])->group('uid')->having("sum >= {$sys['tg_condition']}")->select();
            $people = count($recU);
        }else{
            $people = UserModel::where("rec_id", $uid)->count();
        }

        return $people;
    }

    /**
     * 获取指定代理下级代理id，平均分配
     * @param $uid,用户id
     * @param boolean $isReturn 是否强制返回
     * @return mixed
     */
    public static function getChildAgentUserId($uid, $isReturn = false){
        $_uid = 0;#用于平均分配，每次寻找下一个
        $apiRedis=new ApiRedis();
        $redisKey = 'get_child_agent_user_id_'.$uid;
        if( $apiRedis->has($redisKey) ){
            $_uid = $apiRedis->get($redisKey);
        }
        //获取子代理uid
        $condition = [];
        $condition[] = ['a.rec_id', '=', $uid];
        $condition[] = ['a.id', '>', $_uid];
        $condition[] = ['b.status', '=', 3];
        $condition[] = ['b.type', '=', 1];
        $childUid = UserModel::alias('a')
            ->join("dq_user_agent b","a.id=b.uid")
            ->where($condition)
            ->order('a.id asc')->value('a.id') ?: 0;
        //设置缓存3个月，下次进来查找从这个id往后找
        $apiRedis->set($redisKey, $childUid, 86400 * 31 * 3);
        if( !$childUid && !$isReturn ){
            $childUid = self::getChildAgentUserId($uid, true);
        }
        return $childUid;
    }

    /**
     * 获取代理条件
     * @param string $field 字段名
     * @param boolean $isFirstWhere 是否是首个条件，不是第一次条件前面会加上 AND
     * @param string $alias 别名
     * @param bool $isEq 是否相等
     * @param string $recCode 邀请码，特定情况只有邀请码没有登录状态
     * @param boolean $isNeedZero 是否需要为零的条件
     * @return string 例：(agent_id = 0 or agent_id=1)
     */
    public static function getAgentWhere($field = 'agent_id', $isFirstWhere = true, $alias = '', $isEq = true, $recCode = '', $isNeedZero = true){
        $uid = get_user_id_no_enforce_login();
        $tree = '';
        //判断用户
        if( $uid ){
            $tree = UserModel::where("id", $uid)->value('tree');
        }else{
            $params = request()->param();
            $_recCode = '';
            if( isset($params['share_code_global']) && $params['share_code_global'] ){
                $_recCode = $params['share_code_global'];
            }elseif ($recCode){
                $_recCode = $recCode;
            }
            if( $_recCode ){
                $tree = UserModel::where('rec_code', '=', $_recCode)->value('tree');
            }
            if(!$_recCode){
                //邀请码没有则根据域名获取代理id
                $agentId = WebSiteConfigService::getRecIdByDomain();
                if($agentId){
                    $tree = UserModel::where('id', '=', $agentId)->value('tree');
                }
            }
        }
        $agentIdArr = [];
        if($tree){
            $agentIdArr = array_filter(explode('/', $tree));
        }
        if( $alias ){
            $alias = $alias.'.';
        }
        //根据域名获取代理id
//        $agentId = self::getAgentIdByWebSite();
        if( !$agentIdArr ){
            if( $isNeedZero ){
                return $isFirstWhere ? "{$alias}{$field} = 0" : " AND {$alias}{$field} = 0";
            }else{
                return '';
            }
        }
        $whereStr = '';
        if( !$isFirstWhere ){
            $whereStr = ' AND ';
        }
        if( $isEq ){
            if( $isNeedZero ){
                $whereStr .= "{$alias}{$field} = 0 or ";
            }
            foreach ($agentIdArr as $v){
                $whereStr .= "find_in_set({$v},{$alias}{$field}) or ";
            }
            //去掉最后一个or
            $whereStr = substr($whereStr, 0, -4);
        }else{
            if ( $isNeedZero ){
                $whereStr .= "{$alias}{$field} = 0 or (";
            }
            foreach ($agentIdArr as $v){
                $whereStr .= "!find_in_set({$v},{$alias}{$field}) and ";
            }
            //去掉最后一个and
            $whereStr = substr($whereStr, 0, -5);
            if ( $isNeedZero ){
                $whereStr .= ')';
            }
        }
        return $whereStr;
    }

    /**
     * 获取代理条件
     * @param string $field 字段名
     * @param boolean $isFirstWhere 是否是首个条件，不是第一次条件前面会加上 AND
     * @param string $alias 别名
     * @param int $uid 用户id
     * @param bool $isEq 是否相等
     * @return string 例：(agent_id = 0 or agent_id=1)
     * @param boolean $isNeedZero 是否需要为零的条件
     */
    public static function getAgentWhereUid($field = 'agent_id', $isFirstWhere = true, $alias = '',$uid = 0, $isEq = true, $isNeedZero = true){
        $tree = '';
        //判断用户
        if( $uid ){
            $tree = UserModel::where("id", $uid)->value('tree');
        }else{
            //先根据邀请码获取
            $params = request()->param();
            if( isset($params['share_code_global']) && $params['share_code_global'] ){
                $tree = UserModel::where('rec_code', '=', $params['share_code_global'])->value('tree');
            }else{
                //邀请码没有则根据域名获取代理id
                $agentId = WebSiteConfigService::getRecIdByDomain();
                if($agentId){
                    $tree = UserModel::where('id', '=', $agentId)->value('tree');
                }
            }
        }
        $agentIdArr = [];
        if($tree){
            $agentIdArr = array_filter(explode('/', $tree));
        }
        //根据域名获取代理id
//        $agentId = self::getAgentIdByWebSite();
        if( $alias ){
            $alias = $alias.'.';
        }
        if( !$agentIdArr ){
            if( $isNeedZero ){
                return "({$alias}{$field} = 0 or {$alias}{$field} = '')";
            }else{
                return '';
            }
        }
        $whereStr = '';
        if( !$isFirstWhere ){
            $whereStr = ' AND ';
        }
        if( $isEq ){
            if( $isNeedZero ){
                $whereStr .= "({$alias}{$field} = 0 or {$alias}{$field} = '') or ";
            }
            foreach ($agentIdArr as $v){
                $whereStr .= "find_in_set({$v},{$alias}{$field}) or ";
            }
            //去掉最后一个or
            $whereStr = substr($whereStr, 0, -4);
        }else{
            if ( $isNeedZero ){
                $whereStr .= "({$alias}{$field} = 0 or {$alias}{$field} = '') or (";
            }
            foreach ($agentIdArr as $v){
                $whereStr .= "!find_in_set({$v},{$alias}{$field}) and ";
            }
            //去掉最后一个and
            $whereStr = substr($whereStr, 0, -5);
            if ( $isNeedZero ){
                $whereStr .= ')';
            }
        }
        return $whereStr;
    }

    //根据邀请码获取代理id
    public static function getAgentIdByRecCode($recCode = ''){
        if( !$recCode ){
            return 0;
        }
        //先从缓存获取
        $apiRedis=new ApiRedis();
        $redisKey = 'get_agent_user_id_by_code_'.$recCode;
        if( $apiRedis->has($redisKey) ){
            return $apiRedis->get($redisKey);
        }
        //没有从数据库获取
        $condition = [];
        $condition[] = ['rec_code', '=', $recCode];
        $agentId = UserModel::where($condition)->value('id') ?: 0;
        if( $agentId ){
            $apiRedis->set($redisKey, $agentId, 86400 * 10);
        }
        return $agentId;
    }

    //获取代理id
    public static function getAgentIdByWebSite(){
        $agentId = 0;
        $uid = get_user_id_no_enforce_login();
        if( $uid ){
            //有用户id情况取上级id
            $userDetail = UserModel::where("id", $uid)->field('rec_id,tree')->find();
            if( $userDetail ){
                //判断上级是否设置了代理,
                $agentId = self::judgeIsSetWebSite($userDetail->rec_id);
            }
        }else{
            //根据域名获取代理id
            $agentId = WebSiteConfigService::getRecIdByDomain();
            //根据邀请码获取
            $params = request()->param();
            $recCode = '';
            if( isset($params['share_code_global']) && $params['share_code_global'] ){
                $recCode = $params['share_code_global'];
            }
            if( isset($params['rec_code']) && $params['rec_code'] ){
                $recCode = $params['rec_code'];
            }
            if( $recCode ){
                //邀请码代理
                $_agentId = self::getAgentIdByRecCode($recCode);
                //判断是否设置了站点权限（根据上级一个一个找，判断整条线）
                $__agentId = self::judgeIsSetWebSite($_agentId);
                if( $__agentId != $agentId ){
                    //如果邀请码的用户有站点权限，则代理id取邀请码用户的
                    $agentId = $_agentId;
                }
            }
        }
        return $agentId;
    }

    //获取配置了站点的代理id(无限上级)
    private static function judgeIsSetWebSite($agentId){
        $apiRedis = new ApiRedis();;
        $redisKey = 'judge_is_set_website_config_agent_id_'. $agentId;
        if( $apiRedis->has($redisKey) ){
            //这里不能设缓存，设置缓存，后台修改时一整条线都需要更改数据
//            return $apiRedis->get($redisKey);
        }
        //从该用户tree获取，逻辑为从大到小找，没有的话则说明都没有开启站点权限
        $userDetail = UserModel::where("id", $agentId)->find();
        if( !$userDetail ){
            return 0;
        }
        $tree = $userDetail->tree;
        $treeArr = array_filter(explode('/', $tree));
        $treeArr = array_filter($treeArr);
        $condition = [];
        $condition[] = ['website_uid', 'in', $treeArr];
        $condition[] = ['website_status', '=', 1];
        $userAgentDetail = UserAgentConfigModel::where($condition)->field('website_uid')->order('website_uid desc')->find();
        if( !$userAgentDetail ){
            return 0;
        }
        return $userAgentDetail->website_uid;


        //判断上级是否设置了代理
//        $condition = [];
//        $condition[] = ['website_uid', '=', $agentId];
//        $condition[] = ['website_status', '=', 1];
//        if( UserAgentConfigModel::where($condition)->value('id') ){
        if( self::judgeIsSiteAgentByAgentId($agentId) ){
            $_agentId = $agentId;
        }else{
            $_agentId = UserModel::where("id", $agentId)->value('rec_id');
            if( !$_agentId ){
                $_agentId =  $agentId;
            }else{
                //循环查询
                $_agentId = self::judgeIsSetWebSite($_agentId);
            }
        }
        $apiRedis->set($redisKey, $_agentId, 86400 * 10);
        return $_agentId;
    }

    //获取代理站点配置
    public static function getAgentWebSiteConfig(){
        $agentId = self::getAgentIdByWebSite();
        if( !$agentId ){
            return [];
        }
        $apiRedis = new ApiRedis();
        $redisKey = 'get_web_site_config_'.$agentId;
        if( $apiRedis->has($redisKey) ){
            return $apiRedis->get($redisKey);
        }
        $condition = [];
        $condition[] = ['website_uid', '=', $agentId];
        $condition[] = ['website_status', '=', 1];
        $webSiteConfig = UserAgentConfigModel::where($condition)->find() ?: [];
        if( $webSiteConfig ){
            $webSiteConfig = $webSiteConfig->toArray();
            $webSiteConfig = hidden_array_data($webSiteConfig, [
                'create_time', 'update_time', 'website_uid', 'website_status', 'id'
            ]);
        }
        $apiRedis->set($redisKey, $webSiteConfig, 60 * 5);
        return $webSiteConfig;
    }

    //判断域名是否绑定代理,域名格式必须为:https://test.aaa.com
    public static function judgeDomainIsBindAgent($domain){
        $apiRedis = new ApiRedis();
        $redisKey = 'judge_domain_is_bind_agent_'. $domain;
        if( $apiRedis->has($redisKey) ){
            return $apiRedis->get($redisKey);
        }
        //判断上级是否设置了代理
        $condition = [];
        $condition[] = ['website_status', '=', 1];
        $condition[] = ['website_web_url', 'like', '%'.$domain.'%'];
        $userAgentDetail = UserAgentConfigModel::where($condition)->field('website_web_url')->find();
        if( $userAgentDetail ){
            $domainStr = $userAgentDetail->website_web_url;
            $data = explode(',', $domainStr);
            $apiRedis->set($redisKey, $data, 86400 * 2);
            return $data;
        }
        $apiRedis->set($redisKey, false, 86400);
        return false;
    }

    //判断是否需要0条件
    public static function judgeIsNeedZeroWhere($uid){
        //获取域名或用户对应的代理对应代理条件
        //判断是否需要agent为0条件
        $registerDomain = MemberService::getRegisterDomain($uid);
        $isNeedZeroWhere = true;
        $agentDomainArr = MemberService::getAgentDomainArr(true);
        if(
            env('AGENT_IS_SHOW_TOTAL_SITE_ACTION', 1) == 0 &&
            in_array($registerDomain, $agentDomainArr) &&
            AgentService::judgeDomainIsBindAgent($registerDomain)
        ){
            //条件1 代理是否显示总代活动开关
            //条件2 用户注册域名(登录状态)，访问域名（未登录）等于env中设置的代理域名
            //条件3 域名绑定了对应代理
            //域名为代理站的则不查询代理id为0的
            $isNeedZeroWhere = false;
        }
        return $isNeedZeroWhere;
    }

    //根据id判断是否是站点代理
    public static function judgeIsSiteAgentByAgentId($agentId){
        $apiRedis = new ApiRedis();
        $redisKey = 'judge_is_site_agent_by_agent_id_'. $agentId;
        if( $apiRedis->has($redisKey) ){
            return $apiRedis->get($redisKey);
        }
        $condition = [];
        $condition[] = ['website_uid', '=', $agentId];
        $condition[] = ['website_status', '=', 1];
        $isSiteAgent = 0;
        if( UserAgentConfigModel::where($condition)->field('id')->find() ) {
            $isSiteAgent = 1;
        }
        $apiRedis->set($redisKey, $isSiteAgent, 86400 * 2);
        return $isSiteAgent;
    }

}