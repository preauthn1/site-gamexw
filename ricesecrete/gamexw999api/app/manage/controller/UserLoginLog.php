<?php
namespace app\manage\controller;
use app\common\lib\Uploads;
use app\common\model\UserLoginModel;
use app\common\lib\Respons;
use app\common\Utils;
use think\facade\View;
use think\Request;
use UAParser\Parser;
use app\manage\factory\UserloginlogFactory;

class UserLoginLog extends Base
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(Request $request)
    {
        $rsps = (new UserloginlogFactory($request->token->uid))->list();
        return $rsps;
    }

    //导出数据
    public function excel(Request $request){
        $param= $request->param();
        $uid = $param['uid'] ?? 0;
        $where=[];
        $UserLoginModel = new UserLoginModel();
        if ($uid > 0) {
            $where[] = ["a.uid", '=', $uid];
        }
        if (isset($param['key']) && $param['key']!='') {
            $where[] = ["a.login_ip|b.username|b.mail|b.phone", '=', $param['key']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.login_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.login_time","<=", Utils::getQueryDateTime($date[1], true)];
        }
        $res=$UserLoginModel
            ->alias('a')
            ->join('dq_user b', 'a.uid=b.id')
            ->where($where)
            ->order("id desc")
            ->field('a.*,b.username,b.mail,b.phone')
            ->select();
//        $state = [1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过'),4=>lang('手动入款')];
        $data = [];
        if (!empty($res)) {
            // 从 A2 开始填充数据
            foreach ($res as $k => $v) {
                $data[] = [
                    $v['username'],$v['mail'],$v['phone'],$v['login_ip'],$v['login_time'],$v['device']
                ];
            }
        }

        $header = [
            lang('用户名'),lang('邮箱'),lang('手机号'),lang('登录ip'),lang('登录时间'),lang('登录设备'),
        ];
        $file_name = genCsv($header, $data,lang('会员登录日志').date('YmdHis',time()), false);
        $s3_url = Uploads::uploadS3(public_path() . $file_name);
        Utils::addOptLogByHandle('会员登录日志导出');
        return $this->success([
            'data' => $s3_url ?: toMedia($file_name, '/')
        ]);
    }
}