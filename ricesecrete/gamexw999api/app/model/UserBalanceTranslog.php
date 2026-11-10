<?php


namespace app\model;


use think\Model;

class UserBalanceTranslog extends Model {

    protected $name = 'user_balance_translog';
    protected $pk = 'ubtl_id';

    //存日志记录字段
    public function getField(){
        $field = [
            'ubtl_id'=>'id',
            'ubtl_type'=>lang('转账类型 0空置 1充值 2提现 3平台转游戏 4游戏转平台'),
            'ubtl_tcid'=>lang('type=3|4时的相关三方平台ID'),
            'ubtl_uid'=>lang('用户ID'),
            'ubtl_oldban'=>lang('用户平台账户旧余额'),
            'ubtl_newban'=>lang('用户平台账户新余额'),
            'ubtl_amount'=>lang('转账金额（正数）'),
            'ubtl_intime'=>lang('转账时间'),
            'ubtl_status'=>lang('状态：0 空置 1准备执行 2失败 3成功'),
            'ubtl_errproc'=>lang('错误处理时间'),
            'ubtl_errprocinfo'=>lang('错误处理信息'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }
}
