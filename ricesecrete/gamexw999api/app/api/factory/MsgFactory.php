<?php
namespace app\api\factory;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\lib\Util;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;

class MsgFactory
{
    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->param=Request::param();
        $this->uid=$uid;
    }

    //未读/总消息数量
    public function unread_run(){
        $where  = "uid=".$this->uid;
        $data['count']=UserMsgModel::where($where)->where("read",0)->field("id")->count();
        $data['system_count']=UserMsgModel::where('type', '=', 13)->where('!find_in_set("'. $this->uid. '",read_uid)')->count();
        $data['total_count']=$data['count']+$data['system_count'];
        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }
    
    //消息详细
    public function detail_run(){
        $msg_id = $this->param['msg_id']??0;
        $lang = $this->param['lang']??"CN";
        if(empty($msg_id)){
            ajax_return("参数错误", 0);
        }
        $type = UserMsgModel::where("id",$msg_id)->value('type');
        $wh = $type == 13 ? ['id'=>$msg_id]:['uid'=>$this->uid,'id'=>$msg_id];

        $data = UserMsgModel::where($wh)->field("id,title,content,add_time,type,othr_id,type,data")->find();
        if(empty($data)){
            ajax_return("数据不存在", 0);
        }
        $da = $data['data'] ? json_decode($data['data'],true) : [];
        $data['title'] = lang($data['title'],$da);
        $data['content'] = lang($data['content'],$da);
        $data['title'] = strtr($data['title'], array(
                "系统消息" => lang('系统消息'),
                "购买注单" => lang('购买注单'),
                "包赔跟投" => lang('game32'),
                "推荐跟投" => lang('game48'),
                "优惠审批" => lang('优惠审批'),
                "注单退回" => lang('注单退回'),
                "优惠申请" => lang('优惠申请'),
                "充值送积分" => lang('充值送积分'),
                "活期基金转出" => lang('活期基金转出'),
                "活期基金转入" => lang('活期基金转入'),
                "定期基金购买" => lang('定期基金购买'),
                "定期基金返还" => lang('定期基金返还'),
                "中奖金额" => lang('中奖金额'),
                "抽奖金额" => lang('抽奖金额'),
                "抽奖积分" => lang('抽奖积分'),
                "活期基金收益" => lang('活期基金收益'),

        ));

        $data['content'] = strtr($data['content'], array(
                "系统消息" => lang('系统消息'),
                "购买注单" => lang('购买注单'),
                "下注成功" => lang('下注成功'),
                "主队" => lang('game56'),
                "客队" => lang('game57'),
                "注册首存申请成功" => lang('注册首存申请成功'),
                "体验金审批未通过。具体原因请联系客服" => lang('体验金审批未通过。具体原因请联系客服'),
                "注册首存审批通过。请查收。" => lang('注册首存审批通过。请查收。'),
                "再存优惠申请成功" => lang('再存优惠申请成功'),
                "一般优惠申请成功" => lang('一般优惠申请成功'),
                "体验金申请成功" => lang('体验金申请成功'),
                "积分入账" => lang('积分入账'),
                "活期基金转出成功,转出金额" => lang('活期基金转出成功,转出金额'),
                "活期基金转入成功,转入金额" => lang('活期基金转入成功,转入金额'),
                "定期余额基金买入成功" => lang('定期余额基金买入成功'),
                "定期基金返还成功,返还金额" => lang('定期基金返还成功,返还金额'),
                "抽奖金额扣除" => lang('抽奖金额扣除'),
                "抽奖积分扣除" => lang('抽奖积分扣除'),
                "用户操作撤销" => lang('用户操作撤销'),
                "活期基金收益,收益金额" => lang('活期基金收益,收益金额'),
                "定期基金买入成功,买入金额" => lang('定期基金买入成功,买入金额'),
                "奖品" => lang('奖品'),
                "中奖金额" => lang('中奖金额'),
                "已入账" => lang('已入账'),

        ));

        $data['add_time'] = Util::i18n_datetime2str($data['add_time']);
        $data['sc_detail'] = '';

        if($type != "13"){
            UserMsgModel::where("uid",$this->uid)->where("id",$msg_id)->update(["read"=>1]); // 更新未读状态
        }
        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }

    //消息列表
    public function list_run(){
        $pagenow = $this->param['page']??1;
        $unread = $this->param['unread']??0;
        $limit = $this->param['limit']??10;
//        $lang = $this->param['lang']??"CN";

        $where = "uid=".$this->uid." and type != 13";
        if ($unread == 1) {
            $where .= " AND `read` = 0";
        }
        $data = UserMsgModel::where($where)->limit((($pagenow-1)*$limit),$limit)->field("id,title,add_time,content,type,read,data,recharge_first,link")->order("read asc, add_time desc")->select();
        foreach($data as &$val){
            $msg_data = $val['data'] ? json_decode($val['data'],true) : [];
            if (isset($msg_data['currency'])) {
                $currency_code = Config::get('custom.currency_code');
                $msg_data['currency'] = $currency_code[$msg_data['currency']] ?? Util::i18n_currency_code();
            }
            $val['data'] = $msg_data;
            $val['content'] = lang($val['content'], $msg_data);
            $val['title'] = lang($val['title'], $msg_data);
            $val['title'] = strtr($val['title'], array(
                "购买注单" => lang('购买注单'),
                "包赔跟投" => lang('game32'),
                "推荐跟投" => lang('game48'),
                "优惠审批" => lang('优惠审批'),
                "注单退回" => lang('注单退回'),
                "优惠申请" => lang('优惠申请'),
                "充值送积分" => lang('充值送积分'),
                "活期基金转出" => lang('活期基金转出'),
                "活期基金转入" => lang('活期基金转入'),
                "定期基金购买" => lang('定期基金购买'),
                "定期基金返还" => lang('定期基金返还'),
                "中奖金额" => lang('中奖金额'),
                "抽奖金额" => lang('抽奖金额'),
                "抽奖积分" => lang('抽奖积分'),
                "活期基金收益" => lang('活期基金收益'),
            ));

            $val['content'] = strtr(strip_tags($val['content']), array(
                "购买注单" => lang('购买注单'),
                "下注成功" => lang('下注成功'),
                "主队" => lang('game56'),
                "客队" => lang('game57'),
                "注册首存申请成功" => lang('注册首存申请成功'),
                "体验金审批未通过。具体原因请联系客服" => lang('体验金审批未通过。具体原因请联系客服'),
                "注册首存审批通过。请查收。" => lang('注册首存审批通过。请查收。'),
                "再存优惠申请成功" => lang('再存优惠申请成功'),
                "一般优惠申请成功" => lang('一般优惠申请成功'),
                "体验金申请成功" => lang('体验金申请成功'),
                "积分入账" => lang('积分入账'),
                "活期基金转出成功,转出金额" => lang('活期基金转出成功,转出金额'),
                "活期基金转入成功,转入金额" => lang('活期基金转入成功,转入金额'),
                "定期余额基金买入成功" => lang('定期余额基金买入成功'),
                "定期基金返还成功,返还金额" => lang('定期基金返还成功,返还金额'),
                "抽奖金额扣除" => lang('抽奖金额扣除'),
                "抽奖积分扣除" => lang('抽奖积分扣除'),
                "用户操作撤销" => lang('用户操作撤销'),
                "活期基金收益,收益金额" => lang('活期基金收益,收益金额'),
                "定期基金买入成功,买入金额" => lang('定期基金买入成功,买入金额'),
                "奖品" => lang('奖品'),
                "中奖金额" => lang('中奖金额'),
                "已入账" => lang('已入账'),
            ));
            $val['add_time'] = Util::i18n_datetime2str($val['add_time']);
//            UserMsgModel::where("id",$val['id'])->update(["read"=>1]);
        }
        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }

    //设置已读
    public function set_read_run(){
        $msg_id = $this->param['msg_id']??0;
        $where = [];
        $where['uid'] = $this->uid;
        if(!empty($msg_id)){
            $where['id'] = $msg_id;
        }
        
        UserMsgModel::where($where)->update(["read"=>1]);
        //系统消息已读处理
        if( !$msg_id ){
            //查询所有系统消息
            //read_uid已读用户id串，查询read_uid没有当前用户id的消息，增加当前用户id到read_uid
            $sql = "UPDATE dq_user_msg SET read_uid = CONCAT(read_uid, ',{$this->uid}') where type = 13 and !find_in_set('{$this->uid}',read_uid);";
            Db::execute($sql);
        }
        $rsps['code']=1;
        return $rsps;
    }

    //删除
    public function del_run(){
        $msg_id = $this->param['msg_id']??0;
        if(empty($msg_id)){
            ajax_return("参数错误", 0);
        }
        
        UserMsgModel::where("uid",$this->uid)->where("id",$msg_id)->delete();
        $rsps['code']=1;
        return $rsps;
    }

    //系统消息
    public function system_msg_run(){
        $pagenow = $this->param['page']??1;
        $limit = $this->param['limit']??10;
        $lang = $this->param['lang']??"CN";

        $data = UserMsgModel::where(['type'=>13])->limit((($pagenow-1)*$limit),$limit)->field("id,title,add_time,content,action_id,img,link_title,link,read,read_uid")->order("read asc, add_time desc")->select();
        foreach($data as &$val){
            $val['title'] = lang($val['title']);
            $val['content'] = lang($val['content']);
            $val['add_time'] = Util::i18n_datetime2str($val['add_time']);
            //判断是否已读
            $val['read'] = in_array($this->uid,explode(',',$val['read_uid']))? 1:0;
        }
        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }
	
}