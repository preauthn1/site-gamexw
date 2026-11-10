<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\model\PddPhoneModel;
use app\common\Utils;
use think\Request;
class PddPhone
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $pddPhoneModel =new PddPhoneModel();
        $condition = [];
        if( isset($param['phone']) && $param['phone'] !== '' ){
            $condition[] = ['phone', '=', $param['phone']];
        }
        $paginate=$pddPhoneModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        if (isset($param['export']) && $param['export'] == 1) {
            $list = $rsps['data'];
            $header = [
                'phone' => lang('手机号'),
            ];
            $file_name = genCsv($header, $list,lang('PDD号码导出').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($condition)) {
                Utils::addOptLogByHandle('PDD号码导出');
            }
            return Respons::run(1,$s3_url ?: toMedia($file_name, '/'),'','',$param,new PddPhoneModel(),lang('pdd号码导出'));
        }
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $res = PddPhoneModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new PddPhoneModel(),lang('pdd号码'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $pddPhoneModel = new PddPhoneModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $pddPhoneModel->where('id',$info['id'])->find();
            $res=$pddPhoneModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$pddPhoneModel,lang('pdd号码修改'));
        }
        if (isset($param['find'])) {
            $res=$pddPhoneModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $pddPhoneModel =new PddPhoneModel();
            $res=$pddPhoneModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$pddPhoneModel,lang('pdd号码删除'));
        }
    }

    //导入
    public function import(Request $request){
        $file = $request->param('file/s');
        if (!file_exists(toMedia($file, '', 2))) {
            return Respons::run(0, '', '请上传文件');
        }
        $data = explode("\n", file_get_contents(toMedia($file, '', 2)));
        $dataAll = [];
        $time = time();
        foreach ($data as $v){
            //这里用简单的强制转换int，文字转换后会转换成0，区号会去掉
            $v = (int)$v;
            if( is_numeric($v) && $v != 0 ){
                $dataAll[] = [
                    'phone' => $v,
                ];
            }
        }
        $res = (new PddPhoneModel())->saveAll($dataAll);
        return Respons::run($res?1:0,'','',$file,'',new PddPhoneModel,lang('pdd号码导入'));
    }
}