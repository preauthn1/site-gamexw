<?php
namespace app\manage\controller;

use app\common\model\SmsConfigs;
use app\common\lib\{Respons, Util};
use app\common\model\EmailConfigs as EmailConfigsModel;
use app\common\model\MailNotifyTemplateModel as MailNotifyTemplateModel;
use think\facade\{View, Request, Config};


class EmailConfigs
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
            $rsps['total'] = EmailConfigsModel::where($where)->count();
            $rsps['data'] = EmailConfigsModel::where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
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
            $emailConfigsModel = app()->make(EmailConfigsModel::class);
            $info['sms_content']=isset($info['sms_content'])?json_encode($info['sms_content']):[];
            $res = $emailConfigsModel->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0,'','','',$info,$emailConfigsModel,lang('邮箱渠道添加'));
        }
        return Respons::run(0);
    }

    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $emailConfigsModel = app()->make(EmailConfigsModel::class);
            unset($info['sms_iden']);
            $info['sms_content'] = isset($info['sms_content'])?json_encode($info['sms_content']):[];
            $data = $emailConfigsModel->where('id',$info['id'])->find();
            $res = $emailConfigsModel->update($info);
            return Respons::run($res ? 1 : 0,'','',$data,$info,$emailConfigsModel,lang('邮箱渠道修改'));
        }

        if (isset($param['find'])) {
            $emailConfigsModel =new EmailConfigsModel();
            $res=$emailConfigsModel->where("id",$param['id'])->find();
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
            $res = EmailConfigsModel::where("id","in",$id)->save(['is_del'=>1]);
            return Respons::run($res?1:0,'','',$param,'',new EmailConfigsModel(),lang('邮箱渠道删除'));
        }
        return Respons::run(0);
    }


    public function list_template(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'] ?? 1;
            $limit=$param['limit'] ?? 15;
            $where=[];
            $rsps['total'] = MailNotifyTemplateModel::where($where)->count();
            $rsps['data'] = MailNotifyTemplateModel::where($where)
                            ->filter(function ($d){
                                $arr = [
                                    'verify' => lang('实名验证'),
                                    'off' => lang('充值'),
                                    'with' => lang('提现'),
                                    'admin' => lang('后台发送'),
                                ];
                                $d['sign'] = $arr[$d['sign']];
                            })
                            ->limit((($page-1)*$limit),$limit)
                            ->order("id desc")
                            ->select();
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return Respons::run(0);
    }

    public function add_template(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $MailNotifyTemplateModel = app()->make(MailNotifyTemplateModel::class);
            $res = $MailNotifyTemplateModel->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0,'','','',$info,$MailNotifyTemplateModel,lang('邮箱模板添加'));
        }
        return Respons::run(0);
    }

    public function edit_template(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $MailNotifyTemplateModel = app()->make(MailNotifyTemplateModel::class);
            $data = $MailNotifyTemplateModel->where('id',$info['id'])->find();
            $res = $MailNotifyTemplateModel->update($info);
            return Respons::run($res ? 1 : 0,'','',$data,$info,$MailNotifyTemplateModel,lang('邮箱模板修改'));
        }

        if (isset($param['find'])) {
            $MailNotifyTemplateModel =new MailNotifyTemplateModel();
            $res=$MailNotifyTemplateModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        return Respons::run(0);
    }

    public function delete_template(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $res = MailNotifyTemplateModel::where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',new MailNotifyTemplateModel(),lang('邮箱模板删除'));
        }
        return Respons::run(0);
    }
}