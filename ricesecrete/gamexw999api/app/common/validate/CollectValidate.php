<?php
namespace app\common\validate;
use app\common\model\UserCollectModel;
use think\Validate;
class CollectValidate extends Validate
{
    protected $rule = [
        'page' => 'require',
        'limit' => 'require',
        'type'  =>  'require|checkType',
        'other_id' =>  'require',
    ];

    protected $message  =   [
        'page.require' => '缺少页数',
        'limit.require' => '缺少每页显示数量',
        'type.require' => '缺少重要参数',
        'other_id.require'     => '缺少重要参数',
    ];



    protected $scene = [
        'collect'  =>  ['type','other_id'],
        'list'  =>  ['page', 'limit','type'],
    ];

    //验证类型
    public function checkType($value, $rule, $data){
        if( in_array($value, UserCollectModel::getTypeList()) ){
            return true;
        }
        return false;
    }
}