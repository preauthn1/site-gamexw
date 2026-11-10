<?php
namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\Utils;
use Carbon\Carbon;
use think\facade\Request;
use think\facade\Db;
use app\common\model\JackpotModel;
use app\common\model\JackpotConfigModel;
use app\common\model\JackpotContentModel;
use app\common\model\JackpotRecordModel;

class JackpotFactory
{
    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->param=Request::param();
        if( !$uid ){
            $uid = get_user_id_no_enforce_login();
        }
        $this->uid = $uid;
    }

    //活动列表
    public function list_run(){
        $pagenow=$this->param['page']??1;
        $limit=$this->param['limit']??10;
        $type=$this->param['type']??1;
        $issue = $this->param['issue']??0;
        $config = JackpotConfigModel::jackpot_config($type);
        if(!$config){
            ajax_return("活动不存在");
        }

        $where = [];
        $where[] = ['j_type','=',$config['type']];
        $where[] = ['day', '=', JackpotModel::getTimeByJType($config['type'], $issue)['day']];

        $lists = JackpotModel::where($where);

        $portional = $config['portional_config'];
        //最大排名
        $maxlimit = JackpotModel::getMaxNum($portional);

        $field = 'id,j_type,ranking,uid,username,head_img,price';
        $list = $lists->order('ranking asc')->field($field)->page($pagenow, $limit)->limit($maxlimit)->select();
        $realPrice = $lists->sum('price');
        //奖池金额
        $total_price = $realPrice > $config['starting_amount'] ? $realPrice : $config['starting_amount'];
        $myBet = [
            'ranking' => 0,
            'total_price' => 0,
            'proportion' => 0,
            'previous' => 0,   //距离上一位的金额
            'price' => 0,
        ];
        $rankingPrice = [];#排名对应金额，方便计算自己距离上一名的金额相差多少
        foreach ($list as &$v) {
            //获取返利比例
            $v['proportion'] = 0;
            foreach ($portional as $_v){
                if( $v['ranking'] >= $_v['start'] && $v['ranking'] <= $_v['end'] ){
                    $v['proportion'] = $_v['proportion'];
                    break;
                }
            }

            $v['username'] = hideStr($v['username']);
            $v['total_price'] = Util::i18n_currency($v['price']);
            $v['head_img'] = $v['head_img'] ?: DefaultHeadModel::getHeadOne();
            $rankingPrice[$v['ranking']] = $v['price'];

            //我的下注
            if( $v['uid'] == $this->uid  ){
                $myBet['ranking'] = $v['ranking'];
                $myBet['total_price'] = $v['total_price'];
                $myBet['proportion'] = $v['proportion'];
                $myBet['price'] = $v['price'];
            }
        }

        //计算距离上一名相差的金额
        if( $myBet['ranking'] > 1 ){
            $myBet['previous'] = isset($rankingPrice[$myBet['ranking']-1]) ? $rankingPrice[$myBet['ranking']-1] - $myBet['price'] : 0;
        }

        $rsps['code']=1;
        $rsps['total_price']= $total_price;
        $rsps['my_bet']= $myBet;
        $rsps['data']=$list;
        return $rsps;
    }

    //活动详情
    public function detail_run(){
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $type=$this->param['type']??1;
        $config = JackpotConfigModel::jackpot_config($type);
        if(!$config){
            ajax_return("活动不存在");
        }
        $where = ['j_id'=>$config['id'], 'lang'=>$lang];
        $info = JackpotContentModel::where($where)->withoutField('update_time')->find() ??'';
        $rsps['data']=$info;
        $rsps['code']=1;
        return $rsps;
    }

    //我的奖励
    public function rewards_run(){
        $type=$this->param['type']??'';
        $pagenow=$this->param['page']??1;
        $limit=$this->param['limit']??10;
        $where = ['uid'=>$this->uid];
        if($type){
            $where['type'] = $type;
        }

        $list = JackpotRecordModel::where($where)->limit((($pagenow-1)*$limit),$limit)->order('id desc')->select() ??'';
        $total = JackpotRecordModel::where($where)->count();
        foreach ($list as $k => &$v) {
            $v['price'] = Util::i18n_currency($v['price']);
            $v['proportion'] = $v['proportion'] * 100;
            $time = $v['create_time'];
            $v['add_time'] = Util::i18n_datetime2str(strtotime($time));
            unset($v['create_time']);
        }

        $rsps['data']=$list;
        $rsps['total']=$total;
        $rsps['code']=1;
        return $rsps;
    }


    //排行榜配置状态
    public function config_run(){
        $config = JackpotConfigModel::jackpot_config(1);
        $config2 = JackpotConfigModel::jackpot_config(2);
        $config3 = JackpotConfigModel::jackpot_config(3);
        $data  = [
            'day_status'=> $config ? 1:0,
            'week_status'=> $config2 ? 1:0,
            'month_status'=> $config3 ? 1:0,
        ];
        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }

}