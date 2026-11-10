<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
use think\facade\Cache;

class JackpotConfigModel extends Model
{
    protected $name = 'jackpot_config';
    protected $schema = [
        'id'          => 'int',
        'type'          => 'tinyint',
        'and_time'  =>  'bigint',
        'end_time' =>  'bigint',
        'bet_type' =>  'tinyint',
        'portional_config' =>  'text',
        'level_id' =>  'int',
        'tag_id' =>  'int',
        'agent_id' =>  'varchar',
        'fcharge_status' =>  'tinyint',
        'status' =>  'tinyint',
        'water' =>  'int',
        'create_time' =>  'bigint',
        'update_time' =>  'bigint',
        'starting_amount' =>  'decimal',
        'jackpot_time' =>  'varchar',
        'next_time' =>  'bigint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'type'=>lang('类型'),
            'and_time'=>lang('活动开始时间'),
            'end_time'=>lang('活动结束时间'),
            'bet_type'=>lang('统计类型'),
            'portional_config'=>lang('排名奖金比例配置'),
            'level_id'=>lang('等级id'),
            'tag_id'=>lang('标签id'),
            'agent_id'=>lang('代理id'),
            'fcharge_status'=>lang('首冲下注状态'),
            'status'=>lang('状态'),
            'water'=>lang('流水倍数'),
            'starting_amount'=>lang('起始金额'),
            'jackpot_time'=>lang('结算时间点'),
        ];
        $set_key = [
            ['type'=>[1=>lang('日榜'),2=>lang('周榜'),3=>lang('月榜')]],
            ['bet_type'=>[1=>lang('下注金额'),2=>lang('有效流水')]],
            ['fcharge_status'=>[0=>lang('关闭'),1=>lang('开启')]],
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public static function jackpot_config($id,$action = 1,$update = []){
        $redis = Cache::store('redis');
        $redis_keys = "jackpot_config_{$id}";
        if($action == 1){
            $config = $redis->get($redis_keys);
            if(empty($config)){
                $config = self::where(['type' => $id,'status'=>1])->whereTime('and_time', '<=', time())->whereTime('end_time', '>=', time())->find();
                if($config){
                    if(!empty($config['portional_config'])){
                        $config['portional_config'] = json_decode($config['portional_config'], true);
                    }
                    $redis->set($redis_keys, $config);
                }
            }
            return $config;
        }else{
            self::where(['type' => $id])->update($update);
            $config = self::where(['type' => $id,'status'=>1])->whereTime('and_time', '<=', time())->whereTime('end_time', '>=', time())->find();
            if($config){
                if(!empty($config['portional_config'])){
                    $config['portional_config'] = json_decode($config['portional_config'], true);
                }
                $redis->set($redis_keys, $config);
            }else{
                $redis->delete($redis_keys);
            }
            return $config;
        }
    }

}