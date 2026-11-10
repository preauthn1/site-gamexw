<?php
namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\redis\CommomRedis;
use app\manage\factory\NationFactory;
use app\common\model\{NationModel, LangModel};
use think\facade\{Config, View, Db};
use think\Request;

class Nation
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    /**
     * @param NationModel $NationModel
     */
    public function __construct(NationModel $NationModel)
    {
        $this->NationModel = $NationModel;
    }

    /**
     * 列表
     */
    public function index(\think\Request $request)
    {
        $rsps = (new NationFactory($request->token->uid, $this->NationModel))->index();
        return $rsps;
    }


    /**
     * @param Request $request
     * @return string|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function add(Request $request)
    {
        $param = $request->param();
        if(isset($param['langs'])){
            $langs = LangModel::field('id,lang,is_default')
                ->where(['status' => 1])
                ->order("sort desc")
                ->select();
            return Respons::run(1, $langs);
        }
        if(isset($param['currency'])){
            $currency = Config::get('custom.country');
            return Respons::run(1,$currency);
        }

        if(isset($param['add'])){
            $info = $param['info'];
            try {
                validate(\app\common\validate\NationValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            Db::startTrans();
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该国家是开启的
                $info['state'] = 1;
            }
            try {
                $this->setDefa($info); //检验是否唯一默认国家
                $res = $this->NationModel->save($info);
                Db::commit();
                $this->setRedis();
                $info['lang'] = json_encode($info['lang']);
                return Respons::run($res ? 1 : 0,'','','',$info,$this->NationModel,lang('添加国家'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }

        return Respons::run(0);
    }


    /**
     * @param Request $request
     * @return string|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['find'])) {
            $res= $this->NationModel
                ->where("id",$param['id'])
                ->withoutField('create_time, update_time')
                ->find();
            return Respons::run(1,$res);
        }
        if(isset($param['langs'])){
            $langs = LangModel::field('id,lang,is_default')
                ->where(['status' => 1])
                ->order("sort desc")
                ->select();
            return Respons::run(1, $langs);
        }
        if(isset($param['currency'])){
            $currency = Config::get('custom.country');
            return Respons::run(1,$currency);
        }
        if(isset($param['edit'])){
            $info = $param['info'];
            try {
                validate(\app\common\validate\NationValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该货币是开启的
                $info['state'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认国家
                [$model,$data] = getOneData($this->NationModel,['id' => $info['id']]);
                $res = $model->allowField(['name', 'flag','lang','is_defa','state','sort'])->save($info);
                $info['lang'] = json_encode($info['lang']);
                $data['lang'] = json_encode($data['lang']);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0,'','',$data,$info,$this->NationModel,lang('修改国家信息'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        return Respons::run(0);
    }

    /**
     * 修改状态
     * @param Request $request
     * @return \think\response\Json|void
     */
    public function state_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该货币是开启的
                $info['state'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认币种
                $arr_key =  implode(',',array_keys($info)); //获取要修改的字段
                [$model,$data] = getOneData($this->NationModel,['id' => $info['id']], $arr_key);
                $res = $model->save($info);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0,'','',$data,$info,$this->NationModel,lang('国家状态修改'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        return Respons::run(0);
    }

    /**
     * @param Request $request
     * @return \think\response\Json|void
     * @throws \think\exception\PDOException
     */
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            Db::startTrans();
            try {
                [$model,$data] = getOneData($this->NationModel,['id' => $id]);
                if($data['is_defa'] == 1){
                    return Respons::run(0, [], lang('不允许删除默认国家'));
                }
                $res = $model->delete();
                Db::commit();
                $this->setRedis();
                $data['lang'] = json_encode($data['lang']);
                return Respons::run($res?1:0,'','',$data,'',$this->NationModel,lang('删除国家'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        return Respons::run(0);
    }


    /**
     * 数据库中 只能存在一个 默认国家 并且必须存在默认国家
     * @param $info
     */
    public function setDefa($info){
        if(!isset($info['is_defa'])){
            return false;
        }
        $id = $this->NationModel
            ->where(['is_defa' => 1]) //直接查询有没有默认语言
            ->when(isset($info['id']),function($query) use($info) { //如果没传id 就是添加操作  传了id 就是编辑的操作
                $query->where('id','notIn',$info['id']);
            })->value('id');
        if($id && $info['is_defa'] == 1){
            $this->NationModel->where(['is_defa' => 1])->update(['is_defa'=> 2]);
        }
        if($info['is_defa'] == 2 && !isset($id)){ //无论添加编辑 如果系统没有默认币种  就会提示
            throw new \think\Exception(lang('必须存在一个默认国家'), 0);
        }
    }


    /**
     * 增删改查写入redis
     * @return void
     */
    public function setRedis(){
        /* @var CommomRedis */
        app()->make(CommomRedis::class)->set( "system_nation", Util::getNation(),86400);
        (new CommomRedis())->getCurrency(true);
    }
}
