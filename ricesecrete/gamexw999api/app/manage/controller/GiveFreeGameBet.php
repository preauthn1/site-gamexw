<?php
namespace app\manage\controller;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\AdminLogModel;
use app\common\model\GameApisExtplModel;
use app\common\model\GamesModel;
use app\common\model\UserFreebetsModel;
use app\common\model\UserMsgModel;
use app\common\service\MessageService;
use app\manage\factory\FreeGameFactory;
use app\model\GameConfigs;
use think\Request;

class GiveFreeGameBet extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //赠送免费游戏
    public function give(){
        $staffid=$this->user_id;
        $datas=$this->request->param('info/a');
        $gid = $datas['gid']??0;
        if( !$gid ){
            return $this->error('请选择游戏');
        }
        $uidStr = $datas['uid']??0;
        if( !$uidStr ){
            return $this->error('请选择用户');
        }
        $datas['freebetStarttime'] = strtotime($datas['freebetStarttime']);
        $datas['freebetEndtime'] = strtotime($datas['freebetEndtime']);
        $datas['betrate'] = intval($datas['betrate']);
        $successCount = 0;
        $errorCount = 0;
        foreach (explode(',', $uidStr) as $uid){
            $res = \app\common\FastGame\Base\Game::freeBetsToUser($uid,$gid,$datas,$staffid);
            if( $res !== true && isset($res['msg'])){
                $errorCount++;
            }elseif( !$res ){
                $errorCount++;
            }else{
                $successCount++;
                //通知用户
                //发送站内信
                $title = '免费游戏';
                $url = '/game-detail?id='.$gid;
                UserMsgModel::add_msg($uid, $title, '恭喜您获得免费游戏', 27, $uid, [], 'CN', '', $title, $url);
                //发送新的通知
                MessageService::sendMsg("您有新的消息!", $uid, 'pop');
            }
        }
        $rearData = ['template_data' => $datas];
        if( $errorCount > 0 ){
            $msg = "赠送成功数{$successCount},赠送失败数{$errorCount}";
            $rearData['template_data']['successMsg'] = $msg;
            AdminLogModel::addLog('', json_encode($rearData), 'dq_user_freebets',lang('赠送免费游戏')); //记录日志
            return $this->error($msg);
        }
        return Respons::run(1,'','','', json_encode($rearData),new UserFreebetsModel(),lang('赠送免费游戏'));
    }

    //获取赠送免费游戏的模板数据结构,根据这个结构生成赠送表单
    public function getFreeBetFormTemplate(Request $request){
        $gid=$this->request->param('gid/d');
        if ($gid){
            //找游戏模板
            $tmp=GameApisExtplModel::where('gae_gid',$gid)->find();
            if (!$tmp) {
                $tc = GamesModel::find($gid);
                if ($tc) {
                    $tcid = $tc->g_tcid;
                    $tmp=GameApisExtplModel::where('gae_tcid',$tcid)->where('gae_gid',0)->find();

                    if (!$tmp){
                        return $this->error('当前平台不支持赠送免费游戏');
                    }
                } else {
                    return $this->error('参数错误');
                }
            }
            $formitems=GameApisExtplModel::getFormItems($tmp->toArray());
            $formitems=array_merge([
                'betrate'=>[
                    'title'=>lang('投注倍数/线数'),
                    'type'=>'i',
                    'tip'=>lang('赠送本局游戏的投注倍数或线数，需为大于0的整数，如不确定请勿更改'),
                    'value'=>GameConfigs::getConfigs($gid)->gc_freebet_rate
                ],
                'freebetStarttime'=>[
                    'title'=>lang('免费开始时间'),
                    'type'=>'i',
                    'tip'=>lang('赠送本局游戏的有效开始时间')
                ],
                'freebetEndtime'=>[
                    'title'=>lang('免费结束时间'),
                    'type'=>'i',
                    'tip'=>lang('赠送本局游戏的有效结束时间')
                ],
                'freebetPriceCnt'=>[
                    'title'=>lang('单局赠送总投注额'),
                    'type'=>'n',
                    'tip'=>lang('赠送单局游戏的总投注额，用于系统统计，单局赠送金额(下注金额)，请注意计算正确')
                ],
//                'freebetRounds'
            ],$formitems);
            return $this->success((new FreeGameFactory($request->token->uid))->handleForm($formitems, $gid));
        }else{
            return $this->error('参数错误');
        }
    }
    /*public static function getFreeeBetFormTemplateTest($gid){
        try {
            if ($gid) {
                //找游戏模板
                $tmp = GameApisExtplModel::where('gae_gid', $gid)->find();
                if (!$tmp) {
                    $tc = GamesModel::find($gid);
                    if ($tc) {
                        $tcid = $tc->g_tcid;
                        $tmp = GameApisExtplModel::where('gae_tcid', $tcid)->where('gae_gid', 0)->find();
                        if (!$tmp) {
                            return '参数错误1';
                        }
                    } else {
                        return '参数错误2';
                    }
                }
                $formitems = GameApisExtplModel::getFormItems($tmp->toArray());
                $formitems = array_merge($formitems, [
                    'freebetStarttime' => [
                        'title' => lang('免费开始时间'),
                        'type' => 'i',
                        'tip' => lang('赠送本局游戏的有效开始时间')
                    ],
                    'freebetEndtime' => [
                        'title' => lang('免费结束时间'),
                        'type' => 'i',
                        'tip' => lang('赠送本局游戏的有效结束时间')
                    ],
                    'freebetPriceCnt' => [
                        'title' => lang('单局赠送总投注额'),
                        'type' => 'n',
                        'tip' => lang('赠送单局游戏的总投注额，用于系统统计，单局赠送金额(下注金额)，请注意计算正确')
                    ]
                ]);
                return $formitems;
            } else {
                return '参数错误';
            }
        }catch (\Exception $ex){
            return [$ex->getMessage(),$ex->getTraceAsString()];
        }
    }*/

    //获取免费游戏列表
    public function lists(Request $request){
        $params = $this->request->param();
        $condition = [];
        if (isset($params['username']) && $params['username'] !== '') {
            if (is_numeric($params['username'])) {
                $condition[]=['u.id|u.phone|u.rec_code', "=", $params['username']];
            } else {
                $condition[]=['u.id|u.username|u.mail|u.phone', "=", $params['username']];
            }
        }
        if( isset($params['g_name']) && $params['g_name'] !== '' ){
            $condition[]=['g.gi_name', "like", "%{$params['g_name']}%"];
        }
        if( isset($params['tc_name']) && $params['tc_name'] !== '' ){
            $condition[]=['t.tc_name', "like", "%{$params['tc_name']}%"];
        }
        if( isset($params['min_amount']) && $params['min_amount'] !== '' ){
                $condition[]=['f.ufg_pricecount', ">=", $params['min_amount']];
        }
        if( isset($params['max_amount']) && $params['max_amount'] !== '' ){
            $condition[]=['f.ufg_pricecount', "<=", $params['max_amount']];
        }
        if( isset($params['status']) && $params['status'] !== '' ){
            $condition[]=['f.ufg_state', "=", $params['status']];
        }
        if( isset($params['expire_time']) && $params['expire_time'] !== '' ){
            $dateTime = explode(' - ', $params['expire_time']);
            $condition[] = ['f.ufg_endfreetime', 'between', [strtotime($dateTime[0]),strtotime($dateTime[1])]];
        }
        if( isset($params['give_time']) && $params['give_time'] !== '' ){
            $dateTime = explode(' - ', $params['give_time']);
            $condition[] = ['f.ufg_intime', 'between', [strtotime($dateTime[0]),strtotime($dateTime[1])]];
        }

        $page= $this->request->param('page/d')??1;
        $limit= $this->request->param('limit/d')??10;
        if( isset($params['export']) && $params['export'] == 1 ){
            return (new FreeGameFactory($request->token->uid))->exportList($condition);
        }
        //可扩展其它搜索参数
        $rsps=['code'=>1,'msg'=>'获取成功', 'data'=>[]];
        $baseModel = UserFreebetsModel::alias('f')
            ->join('dq_user u',"u.id=ufg_uid")
            ->join('dq_game_infos g','ufg_gid=gi_gid','left')
            ->join('dq_third_configs t','ufg_tcid=tc_id')
            //->join('dq_game_bets','ufg_usedbygbid=gb_id','left')
            ->where($condition);

        $data= $baseModel
            ->field('f.*,u.username,u.id as uid,u.mail,u.phone,g.gi_name,t.tc_name')
            ->page($page,$limit)
            ->order('ufg_id desc')
            ->select()->toArray();
        //处理时间
        foreach ($data as &$v){
            $v['ufg_intime']=date('Y-m-d H:i:s',$v['ufg_intime']);
            $v['ufg_endfreetime']=date('Y-m-d H:i:s',$v['ufg_endfreetime']);
        }
        $rsps['data'] = $data;

        $rsps['count']= $baseModel->count();
        return Respons::run($rsps);
    }

    public static function liststest($uid=false,$gid=false,$tcid=false,$status=false,$staffid=false,$used=false,$page=1,$limit=10,$date=false,$offset=0,$start_time=false,$end_time=false){
        try {
            //可扩展其它搜索参数
            $rsps = ['code' => 1, 'msg' => '获取成功', 'data' => []];
            $rsps['data'] = UserFreebetsModel::alias('f')
                ->join('dq_user u', "u.id=ufg_uid")
                ->join('dq_admin a', 'a.id=ufg_staffid')
                ->join('dq_game_infos g', 'ufg_gid=gi_gid', 'left')
                ->join('dq_third_configs t', 'ufg_tcid=tc_id')
                //->join('dq_game_bets','ufg_usedbygbid=gb_id','left')
                ->when($uid, function ($query) use ($uid) {
                    $query->where('ufg_uid', $uid);
                })
                ->when($gid, function ($query) use ($gid) {
                    $query->where('ufg_gid', $gid);
                })
                ->when($tcid, function ($query) use ($tcid) {
                    $query->where('ufg_tcid', $tcid);
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('ufg_state', $status);
                })
                ->when($staffid !== null, function ($query) use ($staffid) {
                    $query->where('ufg_staffid', $staffid);
                })
//                ->when($used !== null, function ($query) use ($used) {
//                    if ($used == 0) {
//                        $query->whereNull('ufg_usedbygbsn');
//                    } else {
//                        $query->whereNotNull('ufg_usedbygbsn');
//                    }
//                })
                ->when(!empty($date), function ($query) use ($start_time, $end_time) {
                    $query->whereBetween('ufg_intime', [$start_time, $end_time]);
                })
                ->field('f.*,u.username,u.mail,u.phone,a.username,g.gi_name,t.tc_name')
                ->limit($offset, $limit)
                ->order('ufg_id desc')
                ->select()->toArray();
            $rsps['count'] = UserFreebetsModel::alias('f')
                ->when($uid, function ($query) use ($uid) {
                    $query->where('ufg_uid', $uid);
                })
                ->when($gid, function ($query) use ($gid) {
                    $query->where('ufg_gid', $gid);
                })
                ->when($tcid, function ($query) use ($tcid) {
                    $query->where('ufg_tcid', $tcid);
                })
                ->when($status !== null, function ($query) use ($status) {
                    $query->where('ufg_state', $status);
                })
                ->when($staffid !== null, function ($query) use ($staffid) {
                    $query->where('ufg_staffid', $staffid);
                })
//                ->when($used !== null, function ($query) use ($used) {
//                    if ($used == 0) {
//                        $query->whereNull('ufg_usedbygbsn');
//                    } else {
//                        $query->whereNotNull('ufg_usedbygbsn');
//                    }
//                })
                ->when(!empty($date), function ($query) use ($start_time, $end_time) {
                    $query->whereBetween('ufg_intime', [$start_time, $end_time]);
                })
                ->count();
            return Respons::run($rsps);
        }catch (\Exception $ex){
            return [$ex->getMessage(),$ex->getTraceAsString()];
        }
    }
}