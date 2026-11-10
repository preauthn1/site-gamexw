<?php
namespace app\common\validate;
use think\Validate;
class ActionValidate extends Validate
{
    protected $rule = [
        //'base_name'  =>  'require',
        'base_apply_number_max'  =>  'notIn:0',
        'base_apply_dr_max'  =>  'notIn:0',
        //'c_label'  =>  'require',
        //'c_img'  =>  'require',
        //'c_detail'  =>  'require',

    ];

    protected $message  =   [
        //'base_name.require' => '优惠名称必须',
        'base_apply_number_max.notIn' => '申请上限不能为0',
        'base_apply_dr_max.notIn' => '单人申请上限不能为0',
        //'c_label.require' => '内容标签必须',
        //'c_img.require' => '封面必须',
        //'c_detail.require' => '活动内容必须',
    ];


    // 类型1 验证场景定义
    public function scene1(){	
    	$message=$this->message;
        $message  =   [
            'reg_json.require' => '奖励必须设置',
        ];
        return $this
        ->append("reg_json", "require")
        ->message($message);
    }    


    // 类型3 验证场景定义
    public function scene3(){
    	$message=$this->message;
    	$message  =   [
	        'jl_ck_or_level_s.between' => '等级范围必须',
	        'jl_ck_or_level_e.between' => '等级范围必须',
	        'jl_ck_or_level_s.different' => '领取等级不能相同',
	        'jl_bl_price.notIn' => '固定金额必须',
	    ];

    	return $this
    	->append("jl_ck_or_level_s", "between:1,100|different:jl_ck_or_level_e")
    	->append('jl_ck_or_level_e','between:1,100')
    	->append('jl_bl_price','notIn:0')
    	->message($message);
    }

    // 类型4 验证场景定义
    public function scene4(){	
    	$message=$this->message;
    	$message  =   [
	        'jl_ck_or_level_s.between' => '等级必须',
	        'jl_ck_or_level_e.between' => '等级必须',
            'jl_ck_or_level_s.different' => '领取等级不能相同',
	        'jl_bl_price.notIn' => '体验金必须',
	        'base_ty_ts.require' => '体验天数必须',
	        'base_ty_ts.notIn' => '体验天数不能为0',
	    ];

    	return $this
    	->append("jl_ck_or_level_s", "between:1,100|different:jl_ck_or_level_e")
    	->append('jl_ck_or_level_e','between:1,100')
    	->append('jl_bl_price','notIn:0')
    	->append('base_ty_ts','notIn:0|require')
    	->message($message);
    }

    // 类型6 验证场景定义
    public function scene6(){
        $message=$this->message;
        $message  =   [
            'level_json.require' => '等级比例必须',
        ];

        return $this
            ->append("level_json", "require")
            ->message($message);
    }

    // 类型7 验证场景定义
    public function scene7(){
        $message=$this->message;
        $message  =   [
            'day.require'   => '累积充值天数必须',
            'day.number'    => '累积充值天数只能输入数字',
            'day.egt'       => '累积充值天数必须大于1',
            'money.require' => '每日最低充值金额必须',
            'money.number'  => '每日最低充值金额只能输入数字',
            'money.egt'     => '每日最低充值金额必须大于1',
            'bonus.require' => '奖金必须',
            'bonus.number'  => '奖金只能输入数字',
            'bonus.egt'     => '奖金必须大于1',
        ];

        return $this
            ->append("day", "require|number|egt:1")
            ->append('money','require|number|egt:1')
            ->append('bonus','require|number|egt:1')
            ->message($message);
    }
}