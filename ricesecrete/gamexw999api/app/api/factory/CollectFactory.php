<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\model\GamesModel;
use app\common\model\UserCollectModel;
use app\common\validate\CollectValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Request;

class CollectFactory{

    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid=0){
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //获取收藏列表
    public function collectList(){
        $params = $this->param;
        try {
            validate(CollectValidate::class)->scene('list')->check($params);
        }catch (ValidateException $e){
            ajax_return($e->getError());
        }
        switch ($params['type']){
            case UserCollectModel::GAME_TYPE:
                //游戏
                $data = $this->getCollectGameList($params['page'], $params['limit']);
                break;
            default:
                ajax_return('类型错误');
        }
        return $data;
    }

    //获取收藏游戏列表
    private function getCollectGameList($page = 1, $limit = 10){
        $where = [];
        $where[] = ["a.g_state", ">", 0];
        $where[] = ['uc.user_id', '=', $this->uid];
        $GamesModel = app()->make(GamesModel::class);
        //语言
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $wherestr = '';
        if(!empty($lang)){
            $wherestr = 'c.country not like "%'.$lang.'%" or c.country is null';
        }
        $ApiRedis=new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        $game_number = Db::name('games')->where('g_state', '>', 0)->where('g_show', 1)
            ->field('g_tcid, count(*) AS total')
            ->group("g_tcid")
            ->select()->column('total', 'g_tcid');
        $lists = $GamesModel
            ->alias('a')
            ->join('game_infos i','a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->join('user_collect uc', 'uc.other_id=a.g_id')
            ->where($where)
            ->where($wherestr)
            ->limit((($page-1)*$limit),$limit)
            ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_state as game_status,a.g_tcid,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code,c.tc_name,c.tc_id,a.g_is_new,a.g_is_optimum,a.g_is_self,a.g_exfield,a.g_hot')
            ->order("a.g_sort desc, a.g_id desc")
            ->filter(function($data) use($sys,$game_number){
                if($data['icon'] == '/static/admin/img/add.png'){
                    $data['icon'] = $sys['game_default_pic'];
                }
                if($data['gi_headimg'] == '/static/admin/img/add.png'){
                    $data['gi_headimg'] = $sys['game_default_pic'];
                }
                $data['game_number'] = $game_number[$data['tc_id']] ?? 0;
                $data['is_collect'] = self::judgeIsCollect(UserCollectModel::GAME_TYPE, $this->uid, $data['id']);
            })
            ->select();
        $rsps['data'] = $lists;
        $rsps['code'] = 1;
        return $rsps;
    }

    //判断是否收藏
    public static function judgeIsCollect($type, $uid, $otherId){
        if($uid == 0 || $otherId == 0)return 0;
        $collectRedisKey = UserCollectModel::getRedisKey($uid, $type, $otherId);
        $ApiRedis=new ApiRedis();
        $redisValue = $ApiRedis->get($collectRedisKey);
        if( $redisValue == 1 ){
            //未收藏
            return 0;
        }elseif ( $redisValue == 2 ){
            //已收藏
            return 1;
        }else{
            //没有缓存，从数据库判断
            $condition = [];
            $condition[] = ['type', '=', $type];
            $condition[] = ['user_id', '=', $uid];
            $condition[] = ['other_id', '=', $otherId];
            $_id = UserCollectModel::where($condition)->value('id');
            if( $_id ){
                $ApiRedis->set($collectRedisKey, 2, 86400*10);
                return 1;
            }else{
                $ApiRedis->set($collectRedisKey, 1, 86400*10);
                return 0;
            }
        }
    }

    //收藏
    public function collect(){
        $ApiRedis=new ApiRedis();
        $params = $this->param;
        //验证数据
        try {
            validate(CollectValidate::class)->scene('collect')->check($params);
        }catch (ValidateException $e){
            ajax_return($e->getError());
        }
        //多个操作
        $otherIds = explode(',', $params['other_id']);
        $msg = 'success';
        foreach ($otherIds as $otherId){
            //判断是否收藏
            $collectRedisKey = UserCollectModel::getRedisKey($this->uid, $params['type'], $otherId);
            $condition = [];
            $condition[] = ['type', '=', $params['type']];
            $condition[] = ['user_id', '=', $this->uid];
            $condition[] = ['other_id', '=', $otherId];
            $redisValue = $ApiRedis->get($collectRedisKey);
            if( $redisValue == 1 ){
                //未收藏，该操作为收藏
                COLLECT:
                //没有数据，该操作为收藏
                $res = UserCollectModel::create([
                    'user_id' => $this->uid,
                    'type' => $params['type'],
                    'other_id' => $otherId,
                    'other_data' => $this->getOtherDataByType($params['type'], $otherId),#根据类型获取对应数据
                ]);
                if(!$res)ajax_return('系统错误');
                //添加成功设置缓存为已收藏
                $ApiRedis->set($collectRedisKey, 2, 86400 * 10);
                $msg = '已收藏';
            }elseif( $redisValue == 2 ){
                CANCEL_COLLECT:
                //已收藏，该操作为取消收藏
                //删除数据并将缓存设置成未收藏
                UserCollectModel::where($condition)->delete();
                $ApiRedis->set($collectRedisKey, 1, 86400 * 10);
                $msg = '已取消收藏';
            }else{
                //redis没有收藏数据，判断数据库
                $_userCollectId = UserCollectModel::where($condition)->value('id');
                if( $_userCollectId ) {
                    //存在数据，该操作为取消收藏
                    goto CANCEL_COLLECT;
                }else{
                    //不存在的数据，该操作为收藏
                    goto COLLECT;
                }
            }
        }
        return ['msg' => $msg, 'code' => 1];
    }

    //根据类型获取对应数据
    private function getOtherDataByType($type, $otherId){
        switch ($type){
            case UserCollectModel::GAME_TYPE;
                //游戏
                $otherData = GamesModel::where('g_id', '=', $otherId)->find();
                break;
            default:
                ajax_return('类型错误');
        }
        return json_encode($otherData);
    }


}