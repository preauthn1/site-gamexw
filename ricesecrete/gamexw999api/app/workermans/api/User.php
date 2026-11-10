<?php
namespace app\workermans\api;
use app\common\model\UserModel;
class User
{	
    //在线
    public function ol($data,$token){
        UserModel::is_ol($token->uid,1);
        return [];
    }

    //查询用户余额
    public function balance($data,$token)
    {
        $field = 'balance';
        $user_data = UserModel::where('id', $token->uid)->field($field)->find();
        $data_temp = [
            'balance' => 0
        ];
        if (!empty($user_data)) {
            $data_temp['balance'] = $user_data['balance'];
        }
        return $data_temp;
    }
}