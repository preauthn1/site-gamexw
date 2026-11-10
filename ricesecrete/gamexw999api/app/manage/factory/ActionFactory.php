<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\ActionModel;
use app\common\model\ActionTypeModel;
use app\common\model\ArtificialIntegralModel;
use app\common\model\LotteryConditionRecordModel;
use app\common\model\LotteryConfigModel;
use app\common\model\LotteryPrizeModel;
use app\common\model\LotteryRecordModel;
use app\common\service\WalletService;
use app\common\Utils;
use think\facade\Request;

class ActionFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function lottery_data_record()
    {
        $param=Request::param();
        $where = $arr = [];
        $start_time = time();
        $end_time = time() - 7*86400;
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $end_time = Utils::getQueryDateTime($date[0]);
            $start_time = Utils::getQueryDateTime($date[1], true);
        }

        for($i = $start_time;$i >= $end_time;$i-=86400){
            $date = date("Y-m-d", $i);
            $andtime = strtotime(date('Y-m-d 00:00:00', $i));
            $endtime = strtotime(date('Y-m-d 23:59:59', $i));
            $where[0] = ['a.time','between',[$andtime,$endtime]];
            $where[1] = ['a.is_fake','=',0];


            $arr[$date]['date'] = $date;
            $arr[$date]['lottery_number'] = LotteryRecordModel::alias('a')->where($where)->join('dq_user d','a.uid=d.id and d.lebal=1')->group('a.uid')->count();  //抽奖人数
            $arr[$date]['consumption_amount'] = LotteryRecordModel::alias('a')->where($where)->join('dq_user d','a.uid=d.id and d.lebal=1')->sum('a.price');  //消耗金额
            $arr[$date]['consume_points'] = LotteryRecordModel::alias('a')->where($where)->join('dq_user d','a.uid=d.id and d.lebal=1')->sum('a.integral');  //消耗积分
            $arr[$date]['number_draws'] = LotteryRecordModel::alias('a')->where($where)->join('dq_user d','a.uid=d.id and d.lebal=1')->count();  //抽奖次数
            $arr[$date]['winning_amount'] = LotteryRecordModel::alias('a')->where($where)->join('dq_user d','a.uid=d.id and d.lebal=1')->sum('a.prize_price');  //中奖金额
        }

        foreach($arr as $v){
            if(isset($arr[lang('总计')])){
                $arr[lang('总计')]['date'] = lang('总计');
                $arr[lang('总计')]['lottery_number'] += $v['lottery_number'];
                $arr[lang('总计')]['consumption_amount'] += $v['consumption_amount'];
                $arr[lang('总计')]['consume_points'] += $v['consume_points'];
                $arr[lang('总计')]['number_draws'] += $v['number_draws'];
                $arr[lang('总计')]['winning_amount'] += $v['winning_amount'];
            }else{
                $arr[lang('总计')]['date'] = lang('总计');
                $arr[lang('总计')]['lottery_number'] = $v['lottery_number'];
                $arr[lang('总计')]['consumption_amount'] = $v['consumption_amount'];
                $arr[lang('总计')]['consume_points'] = $v['consume_points'];
                $arr[lang('总计')]['number_draws'] = $v['number_draws'];
                $arr[lang('总计')]['winning_amount'] = $v['winning_amount'];
            }
        }
        $rsps['data'] = $arr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function integral_record()
    {
        $param=Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $accurate = isset($param['accurate'])?$param['accurate']:0;
        $where=[];
        if (isset($param['username']) && $param['username']!='' && $accurate == 0) {
            $where[]=["c.username","like","%".$param['username']."%"];
        }

        if (isset($param['username']) && $param['username']!='' && $accurate == 1) {
            $where[]=["c.username","=", $param['username']];
        }

        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($time[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        if (isset($param['lebal']) && $param['lebal']!='') {
            $where[]=["c.lebal","=",$param['lebal']];
        }

        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }

        if(isset($param['type']) && $param['type'] > 0){
            $where[] = ['a.type','=',$param['type']];
        }
        $rsps['data'] = ArtificialIntegralModel::alias("a")
            ->join("dq_user c","a.uid=c.id")
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,c.username")
            ->paginate($limit,false,['query' => $param]);
        foreach($rsps['data'] as &$val){
            $val['add_time'] =  date("Y-m-d H:i:s",$val['add_time']);
            $val['msg'] = lang($val['msg']);
            $val = Utils::hideUserInfo($val);
        }
        $rsps['code']=1;
        $rsps['pending_price'] = ArtificialIntegralModel::alias("a")->join("dq_user c","a.uid=c.id")->where($where)->where('a.type=1')->sum('a.integral');
        $rsps['distribute_price'] = ArtificialIntegralModel::alias("a")->join("dq_user c","a.uid=c.id")->where($where)->where('a.type=2')->sum('a.integral');
        return Respons::run($rsps);
    }

    public function lottery_record()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where = [];
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[] = ["a.uid|b.phone","=", $param['username']];
            } else {
                $where[] = ["b.username|b.mail|b.phone","=", $param['username']];
            }
        }
        if (isset($param['type']) && $param['type']!='') {
            $where[] = ["a.prize_type","=", $param['type']];
        }
        if (isset($param['lottery_id']) && $param['lottery_id']!='') {
            $where[] = ["a.lottery_id","=", $param['lottery_id']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['is_fake']) && $param['is_fake']!='') {
            $where[] = ["a.is_fake","=", $param['is_fake']];
        }else if(!empty($param['is_fake'])){
            $where[] = ['a.is_fake', '=', 0];
        }

        $rsps['data'] = LotteryRecordModel::alias("a")
            ->join("dq_user b","a.uid = b.id")
            ->join("dq_lottery_config c","a.lottery_id=c.id")
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,b.username,b.mail,b.phone,c.title as lottery_name")
            ->paginate($limit,false,['query' => $param]);

        $prize_type = [1=>lang('金额红包'),2=>lang('实体物品'),3=>lang('谢谢参与')];
        foreach ($rsps['data'] as &$value) {
            $value['time'] = empty($value['time']) ? '':date("Y-m-d H:i:s",$value['time']);
            $value['prize_type'] = isset($prize_type[$value['prize_type']]) ? $prize_type[$value['prize_type']]:'';
            $value['is_free'] = $value['is_free'] == 1 ? '免费':'收费';
            $value = Utils::hideUserInfo($value);
        }
        $data = LotteryPrizeModel::where('stock > 0 or stock = -1')->select();

        $rsps['total_price']= sprintf("%.2f",LotteryRecordModel::alias("a")->join("dq_user b","a.uid = b.id")->where($where)->sum('a.price'));
        $rsps['total_integral']= LotteryRecordModel::alias("a")->join("dq_user b","a.uid = b.id")->where($where)->sum('a.integral');
        $rsps['total_prize_price']= sprintf("%.2f",LotteryRecordModel::alias("a")->join("dq_user b","a.uid = b.id")->where($where)->sum('prize_price'));
        $rsps['lottery'] = LotteryConfigModel::where([])->column('id as value,title as label');
        $rsps['code']=1;
        $rsps['lottery_fakerecord_data'] = $data;
        return Respons::run($rsps);
    }

    public function lottery_condition_record()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where = [];
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[] = ["a.uid|b.phone","=", $param['username']];
            } else {
                $where[] = ["b.username|b.mail|b.phone","=", $param['username']];
            }
        }
        //任务标题筛选
        if (isset($param['title']) && $param['title']!=='') {
            $where[]=["c.news_title","like",'%'.$param['title'].'%'];
        }
        if (isset($param['type']) && $param['type']!=='') {
            $where[] = ["a.type","=", $param['type']];
        }
        if (isset($param['lottery_id']) && $param['lottery_id']!=='') {
            $where[] = ["a.lottery_id","=", $param['lottery_id']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.create_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.create_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        $rsps['data'] = LotteryConditionRecordModel::alias("a")
            ->join("dq_user b","a.uid = b.id")
            ->join("dq_lottery_news c","a.lottery_id=c.lottery_id", 'left')
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,b.username,b.login_ip_count,b.mail,b.phone,c.news_title as lottery_name")
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function lottery_prize()
    {
        $param=Request::param();
        $limit=$param['limit'] ?? 10;
        $where = [];
        if (isset($param['title']) && $param['title']!='') {
            $where[] = ["a.title","like","%".$param['title']."%"];
        }
        if (isset($param['type']) && $param['type']!='') {
            $where[] = ["a.type","=", $param['type']];
        }
        if (isset($param['lottery_id']) && $param['lottery_id']!='') {
            $where[] = ["a.lottery_id","=", $param['lottery_id']];
        }

        $rsps['data'] = LotteryPrizeModel::alias('a')->field('a.*,b.title as lottery_name')->leftJoin("dq_lottery_config b","a.lottery_id=b.id")->where($where)->order("a.sort desc")->paginate($limit,false,['query' => $param]);

        $prize_type = [1=>lang('金额红包'),2=>lang('实体物品'),3=>lang('谢谢参与')];
        foreach ($rsps['data'] as &$value) {
            $value['type'] = isset($prize_type[$value['type']]) ? $prize_type[$value['type']]:'';
        }
        $rsps['lottery'] = LotteryConfigModel::where([])->column('id as value,title as label');
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function lottery_config_find()
    {
        $LotteryConfigModel = new LotteryConfigModel();
        $infos = $LotteryConfigModel->where('id',1)->find();
        if(empty($infos)){
            $LotteryConfigModel->insertGetId(['id'=>1]);
        }
        $starttime = empty($infos['starttime']) ? '':date('Y-m-d H:i:s',$infos['starttime']);
        $endtime = empty($infos['endtime']) ? '':date('Y-m-d H:i:s',$infos['endtime']);
        if($starttime && $endtime){
            $infos['time'] = $starttime.' - '.$endtime;
        }
        return Respons::run(1,$infos);
    }

    public function list()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $ActionModel=new ActionModel();
        $where=[];
        $whereStr = '1=1';
        if (isset($param['dis_code']) && $param['dis_code']!='') {
            $where[]=["a.dis_code","like","%".$param['dis_code']."%"];
        }
        if (isset($param['auto_review']) && $param['auto_review']!='') {
            $where[]=["a.auto_review", "=", $param['auto_review']];
        }
        if (isset($param['type']) && $param['type']!='') {
            $where[]=["a.base_type", "=", $param['type']];
        }
        if (isset($param['apply_date']) && $param['apply_date']!='') {
            $date=explode(' - ',$param['apply_date']);
            $where[]=["a.t_apply_start_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.t_apply_end_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['type_id']) && $param['type_id']!='') {
            $where[]=["a.type_id", "=", $param['type_id']];
        }
        if (isset($param['agent_id']) && $param['agent_id']!='') {
            $whereStr .= ' and find_in_set('.$param['agent_id'].',agent_id)';
        }
        if (isset($param['show_date']) && $param['show_date']!='') {
            $date=explode(' - ',$param['show_date']);
            $where[]=["a.t_show_start_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.t_show_end_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        $rsps = [];
        $field = 'a.*,at.name as action_type_name';
        $rsps['data']=$ActionModel->alias('a')
            ->join('action_type at', 'at.id=a.type_id', 'left')
            ->where($where)->where($whereStr)->field($field)
            ->order("a.sort DESC, a.id desc")
            ->paginate($limit,false,['query' => $param]);

        $action_base_type = config('activity.action_base_type');
        foreach ($rsps['data'] as &$value) {
            $value['add_time'] = date("Y-m-d H:i:s",$value['add_time']);
            $value['t_apply_start_time_date'] = date("Y-m-d H:i:s",$value['t_apply_start_time']);
            $value['t_apply_end_time_date'] = date("Y-m-d H:i:s",$value['t_apply_end_time']);
            $value['t_show_start_time_date'] = date("Y-m-d H:i:s",$value['t_show_start_time']);
            $value['t_show_end_time_date'] = date("Y-m-d H:i:s",$value['t_show_end_time']);
            $value['t_apply_start_time_date2'] = date("Y-m-d",$value['t_apply_start_time']);
            $value['t_apply_end_time_date2'] = date("Y-m-d",$value['t_apply_end_time']);
            $value['t_show_start_time_date2'] = date("Y-m-d",$value['t_show_start_time']);
            $value['t_show_end_time_date2'] = date("Y-m-d",$value['t_show_end_time']);
            $value['base_type_text'] = $action_base_type[$value['base_type']] ?? $value['base_type'];
        }
        $rsps['code']=1;
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['type'] = Utils::getOptionsByConfig($action_base_type);
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['level'] = Utils::getlevel();
        $rsps['url'] = Utils::getUrlList();
        $rsps['action'] = Utils::getOptions('action', ['id', 'dis_code']);
        $rsps['gamesType']= Utils::getOptions('game_type',['id', 'name'],['state'=>1]);
        $rsps['gamesThird']= Utils::getOptions('third_configs',['tc_id', 'tc_name'],['tc_status'=>1]);
        //获取活动类型列表
        $rsps['action_type'] = Utils::getActionTypeList();
        //钱包类型
        $walletSystemData = WalletService::getWalletData();
        $rsps['wallet_type'] = $walletSystemData['wallet_type'] ?? 0;
        array_unshift($rsps['vip'], ['label' => "不限", 'value' => 0]);
        array_unshift($rsps['action'], ['label' => "无", 'value' => 0]);
        return Respons::run($rsps);
    }
}