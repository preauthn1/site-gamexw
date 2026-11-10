<?php
namespace app\api\controller;
use app\common\model\DefaultHeadModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\redis\CommomRedis;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class Reg
{	

    //注册
    public function index(){
        $param=Request::param();
        if (isset($param['reg'])) {
            $info=$param['info'];
            if (empty($info['username'])) {
                ajax_return("请输入账号", 0); 
            }
            if (empty($info['password'])) {
                ajax_return("请输入密码", 0); 
            }
            $username_strlen = strlen($info['username']);
            if ($username_strlen <= 5) {
                ajax_return("账号必须6位或以上", 0);
            }
            $info['rec_code']=Session::get("shareRegCode");
            $CommomRedis=new CommomRedis();
            $sys = $CommomRedis->systemInfo();
            if(!captcha_check($info['code'])){
                return Respons::run(0,[],'验证码错误');
            };
            Db::startTrans();
            try {
                $UserModel=new UserModel();
                $user=$UserModel->where("username",$info['username'])->find();
                if ($user['id']) {
                    ajax_return("账号已存在！", 0);
                }
                $add['rec_id']=0;//推广人id
                $add['reg_ip']=Util::get_ip();
                $add['add_time']=time();
                $add['password']=Util::userMd5Password($info['password']);
                $add['username']=$info['username'];
//                $add['head_img']=$sys['domain']."/api/img/default_head.png";
                $add['head_img']=DefaultHeadModel::getHeadOne();//获取一条头像图片
                $add['birthday']=date("Y-m-d");
                if (!empty($info['rec_code'])) {
                    $rec=$UserModel
                        ->alias("a")
                        ->join("dq_user_agent b","a.id=b.uid")
                        ->where("a.rec_code",$info['rec_code'])
                        ->where("b.status",3)
                        ->field("a.id,a.rec_person")
                        ->find();//要是代理且是通过
                    if (isset($rec['id'])) {
                        $UserModel->rec_person_add($rec['id'], $sys['tg_condition']);
                        $add['rec_id']=$rec['id'];
                    }
                }

                $id=$UserModel->insertGetId($add);

                $upd['id']=$id;
                $upd['rec_code']=Util::rec_code($id);
                $UserModel->update($upd);
                Session::set("shareReg",$upd);
                // 提交事务
                Db::commit();
                $rsps['code']=1;
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                ajax_return($e->getMessage(), 0);
            }
            return Respons::run($rsps);
        }
        return View::fetch('reg/index');
    }

    //验证码
    public function captcha($id = '')
    {   
        return captcha($id);
    }
}