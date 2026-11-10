<?php
namespace app\manage\controller;
use app\common\lib\Util;
use app\common\model\JournalismGameModel;
use app\common\model\JournalismModel;
use app\common\model\JournalismTypeModel;
use app\common\lib\Respons;
use app\common\model\LangModel;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class Journalism
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //分类
    public function type(){
        $param=Request::param();
        if (isset($param['select'])) {
            $limit=$param['limit'];
            $JournalismTypeModel =new JournalismTypeModel();
            $rsps['data']=$JournalismTypeModel
                ->order("sort desc")
                ->paginate($limit,false,['query' => $param]);
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return Respons::run();//View::fetch('journalism/type');
    }

    //分类状态编辑
    public function type_status_edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $JournalismTypeModel=new JournalismTypeModel();
            $data = $JournalismTypeModel->where('id',$info['id'])->field("id,status")->find();
            $res=$JournalismTypeModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$JournalismTypeModel,lang('新闻分类状态修改'));
        }
    }

    //分类删除
    public function type_delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $JournalismTypeModel =new JournalismTypeModel();
            $res=$JournalismTypeModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$JournalismTypeModel,lang('新闻分类删除'));
        }
    }

    //分类添加
    public function type_add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $JournalismTypeModel =new JournalismTypeModel();
            $res=$JournalismTypeModel->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,$JournalismTypeModel,lang('新闻分类添加'));

        }
        return Respons::run();//View::fetch('journalism/type_add');
    }

    //分类编辑
    public function type_edit(){
        $param=Request::param();
        $JournalismTypeModel =new JournalismTypeModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $JournalismTypeModel->where('id',$info['id'])->find();
            $res=$JournalismTypeModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$JournalismTypeModel,lang('新闻分类修改'));
        }
        if (isset($param['find'])) {
            $res=$JournalismTypeModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        return Respons::run();//View::fetch('journalism/type_edit');
    }

    //新闻列表
    public function list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $limit=$param['limit'];
            $where = [];
            $JournalismModel =new JournalismModel();
            if (isset($param['title']) && $param['title']!='') {
                $where[]=["title","like","%".$param['title']."%"];
            }
            if (isset($param['type']) && $param['type']!='') {
                $where[]=["type_id","=",$param['type']];
            }
            $rsps['data']=$JournalismModel->where($where)
                ->order("sort desc, id desc")
                ->filter(function($data){
                    $data['type_id'] = JournalismTypeModel::where(['id'=>$data['type_id']])->value('name');
                    $data['addtime'] = date('Y-m-d H:i:s',$data['addtime']);
                })
                ->paginate($limit,false,['query' => $param]);
            $rsps['code']=1;
            $rsps['type'] = Utils::getOptions('journalism_type');
            $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
            return Respons::run($rsps);
        }
        return Respons::run();//View::fetch('journalism/list');
    }

    //新闻添加
    public function add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $info['addtime'] =time();
            $info['content'] = Request::instance()->param('info.content','',null);
            $JournalismModel =new JournalismModel();
            $gameIds = $info['game_ids'];
            unset($info['game_ids']);
            $info['start_time'] = $info['start_time'] ? strtotime($info['start_time']) : 0;
            $info['end_time'] = $info['end_time'] ? strtotime($info['end_time']) : 0;
            $res=$JournalismModel->insertGetId($info);
            //添加新闻对应游戏表
            $saveAll = [];
            foreach (explode(',', $gameIds) as $v){
                $saveAll[] = ['journalism_id' => $res, 'game_id' => $v];
            }
            (new JournalismGameModel())->saveAll($saveAll);

            $info['addtime'] = date('Y-m-d H:i:s',$info['addtime']);
            $info['id'] = $res;
            $type = JournalismTypeModel::order('sort desc')->select();
            $langs = LangModel::order('id asc')->select();
            $info['type'] = $type;
            $info['langs'] = $langs;
            return Respons::run($res?1:0,'','','',$info,$JournalismModel,lang('新闻添加'));
        }
        return Respons::run();//View::fetch('journalism/add');
    }


    //新闻编辑
    public function edit(){
        $param=Request::param();
        $JournalismModel =new JournalismModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $info['content'] = Request::instance()->param('info.content','',null);
            $data = $JournalismModel->where('id',$info['id'])->find();
            $gameIds = $info['game_ids'];
            unset($info['game_ids']);
            $res=$JournalismModel->update($info);
            //添加新闻对应游戏表
            $saveAll = [];
            foreach (explode(',', $gameIds) as $v){
                $saveAll[] = ['journalism_id' => $info['id'], 'game_id' => $v];
            }
            $journalismGameModel = new JournalismGameModel();
            $journalismGameModel->where('journalism_id', '=', $info['id'])->delete();
            //先删除对应的在添加
            $journalismGameModel->saveAll($saveAll);
            return Respons::run($res?1:0,'','',$data,$info,$JournalismModel,lang('新闻修改'));
        }
        if (isset($param['find'])) {
            $res=$JournalismModel->where("id",$param['id'])->find();
            //获取对应游戏id
            $res['game_ids'] = implode(',', JournalismGameModel::where('journalism_id', '=', $param['id'])->column('game_id'));
            return Respons::run(1, $res);
        }
        return Respons::run();//View::fetch('journalism/edit');
    }


    //新闻删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $JournalismModel = new JournalismModel();
            $res=$JournalismModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$JournalismModel,lang('新闻删除'));
        }
    }

    //新闻状态编辑
    public function status_edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $JournalismModel =new JournalismModel();
            $data = $JournalismModel->where('id',$info['id'])->field("id,status")->find();
            $res=$JournalismModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$JournalismModel,lang('新闻状态修改'));
        }
    }
}