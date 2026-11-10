<?php
namespace app\admin\controller;
use app\common\model\UserLoginModel;
use app\common\lib\Respons;
use think\facade\Session;
use think\facade\View;
use think\Request;
class UserLogin
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(){
        $param = Request::instance()->param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $UserLoginModel =new UserLoginModel();
            $wh['uid'] = $param['uid'];
            $rsps['count']=$UserLoginModel->where($wh)->count();
            $rsps['data']=$UserLoginModel->where($wh)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
            foreach ($rsps['data'] as &$v) {
                $v['login_time']=date("Y-m-d H:i:s", $v['login_time']);
                $v['login_region']=!empty($v['login_ip'])?ip2location($v['login_ip']):'';
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        View::assign('uid',$param['uid']);
        return View::fetch();
    }

}
