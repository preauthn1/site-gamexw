<?php
namespace app\admin\controller;
use app\api\redis\ApiRedis;
use app\common\model\BankModel;
use app\common\lib\Respons;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class Bank
{	
	protected $middleware = ['adminCheckLogin',"Language"];

    //列表
    public function list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $where=[];
            $BankModel =new BankModel();
            if (isset($param['title']) && $param['title']!='') {
    			$where[]=["title","like","%".$param['title']."%"];
    		}
            $rsps['data']=$BankModel
                ->where($where)
                ->order("id desc")
                ->paginate($limit,false,['query' => $param]);
            foreach($rsps['data'] as &$val){
                $val['add_time'] = date("Y-m-d H:i:s",$val['add_time']);
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return View::fetch('bank/list');
    }

    //添加
    public function add(){
    	$param=Request::param();
    	if (isset($param['add'])) {
    		$info=$param['info'];
    		$info['add_time']=time();
            $BankModel=new BankModel();
    		$res=$BankModel->insertGetId($info);
    		return Respons::run($res?1:0);
    	}
        return View::fetch('bank/add');
    }


    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $BankModel=new BankModel();
            $res=$BankModel->update($info);
            //删除用户对应的提现验证
            $withdrawalErrRedisKey = 'withdrawal_error_bank_key_'.$info['uid'].'_'.$info['id'];
            $apiRedis = new ApiRedis();
            $apiRedis->delete($withdrawalErrRedisKey);
            return Respons::run($res?1:0);
        }
        if (isset($param['find'])) {
            $BankModel =new BankModel();
            $res=$BankModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('bank/edit');
    }

    //删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $BankModel =new BankModel();
            $res=$BankModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0);
        }
    }
}