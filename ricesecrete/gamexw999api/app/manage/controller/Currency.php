<?php
namespace app\manage\controller;

use app\common\model\CurrencyModel;
use app\common\redis\CommomRedis;
use app\manage\factory\CurrencyFactory;
use app\common\model\CurrencySetModel;
use app\common\lib\{Respons, Util};
use think\facade\{Config, Session, View, Db};
use think\Request;

class Currency extends Base
{
    public $CurrencyModel = null;
    public function initialize()
    {
        parent::initialize();
        $this->CurrencyModel = new CurrencyModel;
    }

    /**
     * 列表
     * @param Request $request
     * @return string|\think\response\Json
     * @throws \think\exception\DbException
     */
    public function list(\think\Request $request)
    {
        $rsps = (new CurrencyFactory($request->token->uid, $this->CurrencyModel))->list();
        return $rsps;
    }

    /**
     * 添加数据
     * @param Request $request
     * @return string|\think\response\Json
     * @throws \think\exception\PDOException
     */
    public function add(Request $request)
    {
        $param = $request->param();
        if(isset($param['type'])){
            $usdt_type = Config::get('custom.usdt_type');
            //示例 如果要新增需要多个链接名称的 按该格式即可
//            $usdt_type['PIX'] = Config::get('custom.pix_type');
            $code = 1;
            return Respons::run($code,$usdt_type);
        }
        if(isset($param['currency'])){
            $currency = Config::get('custom.country');
            return Respons::run(1,$currency);
        }
        if(isset($param['add'])){
            $info = $param['info'];
            if($info['type'] == 1){
                $one = $this->CurrencyModel->where('type',1)->value('id');
                if($one > 0){
                    return Respons::run(0, [], lang('已存在一个USDT类型的货币'));
                }
            }
            try {
                validate(\app\common\validate\CurrencyValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if ($this->user_id != 1) {
                $info['is_defa'] = 0;
            }
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该货币是开启的
                $info['state'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认币种
                $res = $this->CurrencyModel->save($info);
                Db::commit();
                $info['link_name'] = json_encode($info['link_name']);
                $this->setRedis();
                return Respons::run($res ? 1 : 0,'','','',$info,$this->CurrencyModel,lang('货币添加'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
            return;
        }

    }


    public function edit()
    {
        $param = $this->request->param();
        if(isset($param['find'])){
            $data = $this->CurrencyModel
                    ->withoutField('create_time, update_time')
                    ->where(['id' => $param['id']])
                    ->find();
            $code = 1;
            return Respons::run(compact('code','data'));
        }
        if(isset($param['type'])){
            $usdt_type = Config::get('custom.usdt_type');
            //示例 如果要新增需要多个链接名称的 按该格式即可
//            $usdt_type['PIX'] = Config::get('custom.pix_type');
            $code = 1;
            return Respons::run($code,$usdt_type);
        }
        if(isset($param['currency'])){
            $currency = Config::get('custom.country');
            return Respons::run(1,$currency);
        }
        if (isset($param['edit'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\CurrencyValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if ($this->user_id != 1) {
                unset($info['is_defa']);
            }
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该货币是开启的
                $info['state'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认币种
                [$model,$data] = getOneData($this->CurrencyModel,['id' => $info['id']]);
                $res = $model->allowField(['name', 'country_code','recharge_rate', 'currency_code','withdrawal_rate','link_name','state','is_defa', 'symbol', 'symbol_img'])->save($info);

                $data['type'] = $data['type']['val'];
                $data['link_name'] = json_encode($data['link_name']);
                $info['link_name'] = json_encode($info['link_name']);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0, '','',$data,$info,$this->CurrencyModel,lang('修改货币资料'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
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
            if ($this->user_id != 1) {
                unset($info['is_defa']);
            }
            if(isset($info['is_defa']) && $info['is_defa'] == 1){  //确保开启默认的时候 该货币是开启的
                $info['state'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认币种
                $arr_key =  implode(',',array_keys($info));
                [$model,$data] = getOneData($this->CurrencyModel,['id' => $info['id']], $arr_key);
                $res = $model->save($info);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0,'','',$data,$info,$this->CurrencyModel,lang('货币状态修改'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    /**
     * 数据库中 只能存在一个 默认币种 并且必须存在默认币种
     * @param $info
     * @return void
     */
    public function setDefa($info){
        if(!isset($info['is_defa'])){
            return false;
        }
        $id = $this->CurrencyModel
            ->where(['is_defa' => 1]) //直接查询有没有默认货币
            ->when(isset($info['id']),function($query) use($info) { //如果没传id 就是添加操作  传了id 就是编辑的操作
                $query->where('id','notIn',$info['id']);
            })->value('id');
        if($id && $info['is_defa'] == 1){
            $this->CurrencyModel->where(['is_defa' => 1])->update(['is_defa'=> 2]);
        }
        if($info['is_defa'] == 2 && !isset($id)){ //无论添加编辑 如果系统没有默认币种  就会提示
            throw new \think\Exception(lang('必须存在一个默认币种'), 0);
        }

    }


    /**
     * 每次添加修改都写入redis
     */
    public function setRedis(){
        /* @var CommomRedis $CommomRedis*/
        app()->make(CommomRedis::class)->set( "currency", Util::getCurrency());
    }

    //参数设置
    public function set(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = CurrencySetModel::where("id",1)->find();
            if($data){
                $info['id'] = $data['id'];
                $res = CurrencySetModel::update($info);
            }else{
                $res = CurrencySetModel::insert($info);
            }
            return Respons::run($res?1:0,'','',$data,$info,new CurrencySetModel(),lang('货币参数设置'));
        }

        if (isset($param['find'])) {
            $res = CurrencySetModel::where("id",1)->find();
            if(empty($res)){
                $res['id'] = 1;
                $res['auto_rate'] = 0;
                $res['auto_time'] = 0;
            }
            return Respons::run(1,$res);
        }
    }

}