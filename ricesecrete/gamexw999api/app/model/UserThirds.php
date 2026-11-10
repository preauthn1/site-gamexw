<?php


namespace app\model;


use think\Model;

class UserThirds extends Model {

    protected $name = 'user_thirds';
    protected $pk = '';

    //存日志记录字段
    public function getField(){
        $field = [
            'ut_uid'=>'用户uid',
            'ut_tcid'=>lang('三方平台ID'),
            'ut_trdid'=>lang('用户在三方的key'),
            'ut_trdname'=>lang('用户在三方的名字'),
            'ut_intime'=>lang('首次进入游戏时间'),
            'ut_state'=>lang('游戏状态 0非当前平台 1当前平台'),
            'ut_lastin'=>lang('最后进入时间'),
            'ut_lastout'=>lang('最后退出时间'),
            'ut_url'=>lang('游戏访问地址--弃用'),
            'ut_token'=>lang('用户在第三方游戏的token'),
            'ut_balancein'=>lang('三方平台进入转入金额'),
            'ut_balance'=>lang('三方平台当前余额'),
            'ut_balanceout'=>lang('三方平台退出转出金额'),
            'ut_tokentime'=>lang('用户在第三方平台的token更新时间'),
            'ut_rtp'=>lang('用户rtp'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}
