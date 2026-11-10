<?php
namespace app\manage\controller;
use app\common\model\UserLevelModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\UserLevelDownRecordModel;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\facade\Log;
use think\Request;
class UserLevelDownRecord
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

	//列表
    public function list(Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        //$rewardType = $param['reward_type'] ?? '';
        $title = $param['title'] ?? '';
        $where=[];
        //筛选奖励类型
        /*if( $rewardType !== '' ){
            $where[] = ['ulr.reward_type', '=', $rewardType];
        }*/
        //筛选VIP名称
        if( $title !== '' ){
            $where[] = ['ul.level_name', 'like', '%'.$title.'%'];
        }
        if (isset($param['username']) && $param['username']!='') {
            $where[]=["u.id","=", $param['username']];
//            if (is_numeric($param['username'])) {
//                $where[]=["u.id|u.phone","=", $param['username']];
//            } else {
//                $where[]=["u.username|u.mail|u.phone","=", $param['username']];
//            }
        }
        $UserLevelDownRecordModel =new UserLevelDownRecordModel();
        $rsps['count']=$UserLevelDownRecordModel->alias('ulr')
            ->join('user_level ul', 'ul.id=ulr.level_id')
            ->join('user u', 'u.id=ulr.uid')
            ->where($where)->count();
        $rsps['data']=$UserLevelDownRecordModel->alias('ulr')
            ->join('user_level ul', 'ul.id=ulr.level_id')
            ->join('user u', 'u.id=ulr.uid')
            ->where($where)->limit((($page-1)*$limit),$limit)
            ->field('ulr.*,ul.level_name')
            ->order("ulr.id desc")
            ->paginate($limit,false,['query' => $param]);
        
        foreach($rsps['data'] as &$val){
            $val['time'] = date("Y-m-d H:i:s",$val['time']);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
}