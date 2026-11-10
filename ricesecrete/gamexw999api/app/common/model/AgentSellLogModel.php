<?php
namespace app\common\model;
use app\common\model\AgentSettingModel;
use think\Model;
class AgentSellLogModel extends Model
{
    protected $name = 'agent_sell_log';
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'          => 'int',
        'uid' => 'int',
        'tg_set_id' => 'int',
        'status' => 'int',
        'received_people_num' => 'int',
        'money' => 'decimal',
        'remark' => 'varchar',
        'create_time' => 'bigint'
    ];


    /**
     * 判断是否可以领取推广奖励
     * @param $uid
     * @param $rec_person
    **/
    public static function if_reward($uid,$rec_person){
        $set=AgentSettingModel::where($rec_person." >= stratum_number")->order("id desc")->find();
        if (isset($set['id'])) {
            $log = self::where("uid",$uid)->where("tg_set_id",$set['id'])->find();//查看是否领取推广奖励
            if (!isset($log['id'])) {
                $add['uid']=$uid;
                $add['tg_set_id']=$set['id'];
                $add['money']=$set['stratum_bonus'];
                $add['remark']="推广奖励";
                $add['create_time']=time();
                self::insertGetId($add);
            }
        }
        return true;
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'money'=>lang('领取奖金'),
            'status'=>lang('状态'),
        ];
        $set_key = [
            ['status'=>[1=>lang('待处理'),2=>lang('拒绝'),3=>lang('派发')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }


}