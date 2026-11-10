<?php
namespace app\common\validate;
use think\Validate;
class LiansaiScBdValidate extends Validate
{
    protected $rule = [
        'zd'  =>  'require',
        'kd'  =>  'require',
        'yshl_bfb'  =>  'require',
        'yshl_bs'  =>  'require',
        'zbd_pl'  =>  'require',
        'danzhu_zd'  =>  'require',
        'danzhu_zg'  =>  'require',
        'danxiang_zg'  =>  'require',
    ];

    protected $message  =   [
        'zd.require' => '主队比分必须',
        'kd.require' => '客队比分必须',
        'yshl_bfb.require' => '原始获利必须',
        'yshl_bs.require' => '获利倍数必须',
        'zbd_pl.require' => '反波胆赔率必须',
        'danzhu_zd.require' => '单注最低必须',
        'danzhu_zg.require' => '单注最高必须',
        'danxiang_zg.require' => '单项最高必须',
    ];

    //
    public function sceneInit()
    {
        return $this->only(['zd','kd']);
    }  

     //
    public function sceneEdit()
    {
        return $this->only(['yshl_bfb','yshl_bs','danzhu_zd','danzhu_zg','danxiang_zg','zbd_pl']);
    } 
}