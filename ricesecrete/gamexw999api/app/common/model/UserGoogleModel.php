<?php
namespace app\common\model;
use app\common\exception\MessageException;
use app\common\lib\Authenticator;
use think\facade\Session;
use think\Model;
class UserGoogleModel extends Model
{
    protected $name = 'user_google';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'goodsle_bind'  =>  'tinyint',
        'google_secret' =>  'varchar',
        'login_check' =>  'tinyint',
        'login_password_check' =>  'tinyint',
        'withdrawal_password_check' =>  'tinyint',
        'bankcard_check' =>  'tinyint',
        'withdrawal_check' =>  'tinyint',
    ];

    public static function inser($uid){
        $google = [
            'uid'=>$uid,
            'goodsle_bind'=>0,
            'google_secret'=>"",
            'login_check'=>0,
            'login_password_check'=>0,
            'withdrawal_password_check'=>0,
            'bankcard_check'=>0,
            'withdrawal_check'=>0,
        ];
        self::insert($google);
        return $google;
    }

    public static function verification($uid, $key, $code){
        if (!self::isVerfit($uid,$key)) {
            //没有开启直接返回
            return false;
        }
        $google = self::where(['uid'=>$uid])->field($key.',google_secret')->find();
        if(!empty($google) && $google[$key] == 1){
            if(!$code){
                return '请输入Google验证码';
            }

            $checkResult = self::verifyCode($google['google_secret'], $code);
            if (!$checkResult) {
                return 'Google验证失败';
            }
        }
        return false;
    }

    /**
     * 验证谷歌验证码
     * @param string $google_secret 密钥
     * @param string $code 验证码
     * @return bool
     */
    public static function verifyCode($google_secret, $code){
        if (empty($code) || empty($google_secret)) {
            return false;
        }
        $Authenticator= new Authenticator();
        $checkResult = $Authenticator->verifyCode($google_secret, $code,1);

        return $checkResult;
    }


    /**
     * 判断用户是否开启谷歌验证
     * @param string $uid 优化ID
     * @param string $key 验证码
     * @return bool
     */
    public static function isVerfit($uid, $key){
        return self::where(['uid' => $uid])->value($key);
    }
    //存日志记录字段
    public function getField(){
        $field = [
            'uid'=>lang('用户id'),
        ];
        return ['field'=>$field,'table'=>$this->name];
    }
}