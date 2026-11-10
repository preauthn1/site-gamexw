<?php
namespace app\common\model;
use think\facade\Session;
use app\api\redis\ApiRedis;
use think\Model;
use think\facade\Db;
class UserAgentModel extends Model
{
	protected $name = 'user_agent';
	protected $schema = [
        'id'          => 'int',
        'uid'  =>  'int',
        'status'  =>  'int',
        'apply_ip'  =>  'varchar',
        'admin_name'  =>  'varchar',
        'apply_time'    => 'bigint',
        'remark'    => 'varchar',
        'is_del'    => 'tinyint',
        'update_time'   => 'bigint',
        'rec_id'    => 'int',
        'tree'          => 'varchar',
        'rate'          => 'decimal',
        'type'          => 'tinyint',
        'proxy_topuid'  =>  'int',
        'proxy_upuid'  =>  'int',
        'proxy_tree'  =>  'varchar',
        'proxy_level'  =>  'tinyint',
        'website_auth'  =>  'tinyint',
        'last_name'  =>  'varchar',
        'name'  =>  'varchar',
        'birthday'  =>  'varchar',
        'country'  =>  'varchar',
        'address'  =>  'varchar',
        'province'  =>  'varchar',
        'city'  =>  'varchar',
        'district'  =>  'varchar',
        'zipcode'  =>  'int',
        'phone1'  =>  'varchar',
        'phone2'  =>  'varchar',
        'company_name'  =>  'varchar',
        'company_reg_no'  =>  'varchar',
        'skype'  =>  'varchar',
        'bonus_code'  =>  'varchar',
    ];

    /**
     * 微商报表统计
     * @param  uid
     * return Array data[
     * rec_num 下线总人数
     * first_recharge_num 首充人数
     * succeeding_recharge_num 续充人数
     * recharge_money 充值金额
     * new_reg_num 新注册人数
     * first_recharge_num_percentage 首充占比
     * succeeding_recharge_num_percentage 续充占比
     *
     * ]
     **/
    public static function report_count($uid){

        $today_time =  strtotime(date('Ymd000000'));

        $data = Db::query("select ".
            "COUNT(id) rec_num,".
            "COUNT(if(sc_balance > 0,id,null)) first_recharge_num, ".
            "COUNT(if(xc_balance > 0,id,null)) succeeding_recharge_num, ".
            "SUM(sc_balance + xc_balance) recharge_money, ".
            "COUNT(if(add_time > $today_time,id,null)) new_reg_num ".
            "from ".
            "(select u2.* ".
            "from dq_user u1,dq_user u2 ".
            "where u1.id=$uid and u2.rec_id=u1.id ".
            "union all ".
            "select u3.* from dq_user u3,( ".
            "select u2.* from dq_user u1,dq_user u2 ".
            "where u1.id=$uid and u2.rec_id=u1.id) t2  ".
            "where t2.id=u3.rec_id ".
            "union all ".
            "select u4.* from dq_user u4,( ".
            "select u3.* from dq_user u2,dq_user u3 ".
            "where u2.rec_id=$uid and u3.rec_id=u2.id) t3 ".
            "where t3.id=u4.rec_id ".
            ") a;");

        //首冲人数占比
        $data[0]['first_recharge_num_percentage'] = @bcmul(bcdiv($data[0]['first_recharge_num'],$data[0]['rec_num'],2),100) . "％";

        //复冲人数占比
        $data[0]['succeeding_recharge_num_percentage'] = @bcmul(bcdiv($data[0]['succeeding_recharge_num'],$data[0]['rec_num'],2),100) . "％";

        return $data[0];
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'status'=>lang('状态'),
            'rate'=>lang('收益率'),
            'type'=>lang('类型'),
            'website_auth'=>lang('站点权限'),
        ];
        $set_key = [
            ['status'=>[1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过')]],
            ['type'=>[0=>lang('外部'),1=>lang('内部')]],
            ['website_auth'=>[0=>lang('关闭'),1=>lang('开启')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}