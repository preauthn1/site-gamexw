<?php

namespace app\common\model;

use app\api\factory\PddFactory;
use think\Model;

class PddInviteRecordModel extends Model {

    protected $name = 'pdd_invite_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const TYPE_UNKNOWN = 0;#未知
    const TYPE_TURNTABLE_BALANCE = 1;#转盘余额
    const TYPE_WALLET_BALANCE = 2;#钱包余额
    public static function getTypeArr(){
        return [
            self::TYPE_UNKNOWN,#未知
            self::TYPE_TURNTABLE_BALANCE,#转盘余额
            self::TYPE_WALLET_BALANCE,#钱包余额
        ];
    }

    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'int',
        'type'                      => 'tinyint',
        'username'                  => 'varchar',
        'invite_uid'                => 'int',
        'invite_username'           => 'varchar',
        'turntable_id'              => 'int',
        'amount'                    => 'decimal',
        'status'                    => 'tinyint',
        'withdrawal_amount'         => 'decimal',
        'is_withdrawal'             => 'tinyint',
        'turntable_num'             => 'int',
        'finish_turntable_num'      => 'int',
        'expire_time'               => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('UID'),
            'type'=>lang('类型（0暂无，1转盘余额，2钱包余额）'),
            'username'=>lang('用户username'),
            'invite_uid'=>lang('邀请用户id'),
            'invite_username'=>lang('邀请用户username'),
            'turntable_id'=>lang('对应转盘id'),
            'amount'=>lang('抽奖金额'),
            'status'=>lang('状态'),
            'withdrawal_amount'=>lang('可取款金额'),
            'is_withdrawal'=>lang('是否取款（1已取款，0待取款）'),
            'expire_time'=>lang('过期时间'),
            'turntable_num'=>lang('转盘数量'),
            'finish_turntable_num'=>lang('已转数量'),
        ];
        $set_key = [
            ['type'=>[0=>lang('暂无'), 1=>lang('转盘余额'), 2=>lang('钱包余额')]],
            ['status'=>[0=>lang('未抽奖'), 1=>lang('已抽奖')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    public static function createData($id, $inviteUserDetail){
        //获取该用户正在参与的转盘数据
        $condition = [];
        $condition[] = ['uid', '=', $id];
        $condition[] = ['expire_time', '>=', time()];
        $detail = self::alias('a')->join('pdd_turntable b', 'b.id=a.turntable_id')->field('a.*,b.count_type')->where($condition)->find();
        //用户正在产于才生成数据
        if( $detail ){
            //获取邀请人的数据
            self::create([
                'uid' => $id,
                'username' => hideStr($detail->username),
                'invite_uid' => $inviteUserDetail['id'],
                'invite_username' => hideStr($inviteUserDetail['username']),
                'turntable_id' => $detail->turntable_id,
                'expire_time' => $detail->expire_time,
                'turntable_num' => $detail->count_type == PddTurntableModel::CHILD_REGISTER_COUNT_TYPE ? 1 : 0,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            //清除缓存
            (new PddFactory($id))->clearCache($id, PddTurntableModel::where('id', '=', $detail->turntable_id)->find());
        }
    }
}