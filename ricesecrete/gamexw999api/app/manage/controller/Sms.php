<?php
namespace app\manage\controller;

use app\common\model\SmsConfigs;
use app\common\lib\{Respons, Util};
use think\facade\{View, Request, Config};


class Sms
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'] ?? 1;
            $limit=$param['limit'] ?? 15;
            $where=[];
            $where[] = ['is_del', '=', 0];
            $rsps['total'] = SmsConfigs::where($where)->count();
            $rsps['data'] = SmsConfigs::where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
            foreach ($rsps['data'] as &$v){
                $v['sms_qh'] = $v['sms_qh'] ? explode(',',$v['sms_qh']):[];
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return Respons::run(0);
    }

    //添加
    public function add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $SmsConfigs = app()->make(SmsConfigs::class);
            $info['sms_content']=isset($info['sms_content'])?json_encode($info['sms_content']):[];
            $res = $SmsConfigs->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0,'','','',$info,$SmsConfigs,lang('短信渠道添加'));
        }
        return Respons::run(0);
    }

    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $SmsConfigs = app()->make(SmsConfigs::class);
            unset($info['sms_iden']);
            $info['sms_content'] = isset($info['sms_content'])?json_encode($info['sms_content']):[];
            $data = $SmsConfigs->where('id',$info['id'])->find();
            $res = $SmsConfigs->update($info);
            return Respons::run($res ? 1 : 0,'','',$data,$info,$SmsConfigs,lang('短信渠道修改'));
        }

        if (isset($param['find'])) {
            $SmsConfigs =new SmsConfigs();
            $res=$SmsConfigs->where("id",$param['id'])->find();
            $res['sms_content']=json_decode($res['sms_content'])??[];
            return Respons::run(1,$res);
        }
        return Respons::run(0);
    }

    //删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $res = SmsConfigs::where("id","in",$id)->save(['is_del'=>1]);
            return Respons::run($res?1:0,'','',$param,'',new SmsConfigs(),lang('短信渠道删除'));
        }
        return Respons::run(0);
    }
}