<?php

namespace app\common\model;

use think\Model;

class UserAwardCfgsModel extends Model{

    protected $name = 'user_award_cfgs';

    protected $scene = [
        'uac_id'                        => 'int',
        'uac_awardid'                   => 'int',
        'uac_uid'                       => 'int',
        'uac_intime'                    => 'int',
        'uac_money'                     => 'decimal',
        'uac_rate'                      => 'decimal',
        'uac_water'                     => 'decimal',
        'uac_waterdone'                 => 'decimal',
        'uac_gameids'                   => 'varchar',
        'uac_done'                      => 'int',
        'uac_payed'                     => 'int',
        'uac_paytime'                   => 'int',
    ];

    CONST UAC_TYPE_ACTION = 0;#活动
    const UAC_TYPE_TASK = 1;#任务

    //存日志记录字段
    public function getField(){
        $field = [
            'uac_id'=>'id',
            'uac_awardid'=>lang('优惠类型ID'),
            'uac_uid'=>lang('用户ID'),
            'uac_intime'=>lang('奖励获得时间'),
            'uac_money'=>lang('奖励金额'),
            'uac_rate'=>lang('要求流水倍数'),
            'uac_water'=>lang('要求流水（冗余）'),
            'uac_waterdone'=>lang('已完成流水'),
            'uac_gameids'=>lang('指定游戏统计流水的游戏IDS，用逗号隔开'),
            'uac_done'=>lang('流水完成状态 0 未完成  |完成时间戳'),
            'uac_payed'=>lang('奖励发放状态 0 未发放 1已发放 2不予发放'),
            'uac_paytime'=>lang('奖励发放时间'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    /**
     * 创建cfgs数据（活动专用，最开始创建给活动使用，下面有通用创建方法）
     * @param array $apply 申请数据
     * @param float $amount 金额
     * @param int $appointWaterWalletType 指定流水钱包类型 0真金，1彩金
     * @return true
     * @throws \Exception
     */
    public static function createData($apply, $amount, $appointWaterWalletType = 0){
        //判断活动流水类型
        if( $apply['water_type'] == 1 ){
            //指定平台
            //获取平台对应游戏
            $gameIds = implode(',', GamesModel::where('g_tcid', 'in', $apply['water_type_ids'])->column('g_id'));
        }else{
            //指定游戏
            $gameIds = $apply['water_type_ids'];
        }
        if( !$gameIds ){
            return true;
        }
        $uacid=UserAwardCfgsModel::insertGetId([
            'uac_awardid' => $apply['action_id'],
            'uac_uid' => $apply['uid'],
            'uac_intime' => time(),
            'uac_money' => $amount,
            'uac_rate' => $apply['action_water'],
            'uac_water' => $apply['water'],
            'uac_game_type' => $apply['water_type'],
            'uac_game_type_ids' => $apply['water_type_ids'],
            'uac_gameids' => $gameIds,
            'uac_balancetype' => $appointWaterWalletType,
        ]);
        $writegameIds=explode(',',$gameIds);
        $writedatas=[];
        foreach ($writegameIds as $v){
            $writedatas[] = [
                'ual_uacid' => $uacid,
                'ual_awardid' => $apply['action_id'],
                'ual_uid' => $apply['uid'],
                'ual_gid' => $v,
                'ual_subwater' => 0,
                'ual_subcjwater' => 0,
            ];
        }
        (new UserAwardLogsModel())->saveAll($writedatas);
        return true;
    }


    /**
     * 创建cfgs数据(公共)
     * @param int $uid 用户id
     * @param float $amount 金额
     * @param float $rate 流水倍数
     * @param float $water 要求流水
     * @param int $type 类型(0活动，1任务，其他往后加)
     * @param int $waterType 流水类型（0不支持，1指定平台，2指定游戏）
     * @param string $waterTypeIds 流水类型对应数据id，waterType=1，则为平台id，waterType=2，则为游戏id
     * @param int $sourceId 来源id，type=0时，为活动id，type=1时，为任务id
     * @param int $appointWaterWalletType 指定流水钱包类型 0真金，1彩金
     * @return true
     * @throws \Exception
     */
    public static function publicCreateData($uid, $amount, $rate, $water, $type, $waterType, $waterTypeIds, $sourceId = 0, $appointWaterWalletType = 0){
        if($waterType == 0){
            return true;
        }
        //判断活动流水类型
        if( $waterType == 1 ){
            //指定平台
            //获取平台对应游戏
            $gameIds = implode(',', GamesModel::where('g_tcid', 'in', $waterTypeIds)->column('g_id'));
        }else{
            //指定游戏
            $gameIds = $waterTypeIds;
        }
        if( !$gameIds ){
            return true;
        }
        $uacid=UserAwardCfgsModel::insertGetId([
            'uac_awardid' => $sourceId,
            'uac_type' => $type,
            'uac_uid' => $uid,
            'uac_intime' => time(),
            'uac_money' => $amount,
            'uac_rate' => $rate,
            'uac_water' => $water,
            'uac_game_type' => $waterType,
            'uac_game_type_ids' => $waterTypeIds,
            'uac_gameids' => $gameIds,
            'uac_balancetype' => $appointWaterWalletType,
        ]);
        $writegameIds=explode(',',$gameIds);
        $writedatas=[];
        foreach ($writegameIds as $v){
            $writedatas[] = [
                'ual_uacid' => $uacid,
                'ual_awardid' => $sourceId,
                'ual_uid' => $uid,
                'ual_gid' => $v,
                'ual_subwater' => 0,
                'ual_subcjwater' => 0,
            ];
        }
        (new UserAwardLogsModel())->saveAll($writedatas);
        return true;
    }
}