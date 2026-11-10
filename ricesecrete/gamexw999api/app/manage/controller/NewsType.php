<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\NewsTypeModel;
use app\common\model\PaymentTypeModel;
use app\common\model\PayTypeModel;
use think\facade\Request;

class NewsType
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'];
        $where = [];
        $newsTypeModel = new NewsTypeModel();
        if (isset($param['name']) && $param['name'] !== '') {
            $where[] = ["name", "like", "%" . $param['name'] . "%"];
        }
        $rsps['data'] = $newsTypeModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $newsTypeModel = new NewsTypeModel();
            $res = $newsTypeModel->insertGetId($info);
            return Respons::run($res?1:0,'','','',$info,new NewsTypeModel(),lang('公告类型添加'));
        }
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        $newsTypeModel = new NewsTypeModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $newsTypeModel->where('id',$info['id'])->find();
            $res = $newsTypeModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$newsTypeModel,lang('公告类型修改'));
        }
        if (isset($param['find'])) {
            $res = $newsTypeModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $newsTypeModel = new NewsTypeModel();
            $res = $newsTypeModel->where("id", "in", $id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$newsTypeModel,lang('公告类型删除'));
        }
    }
}