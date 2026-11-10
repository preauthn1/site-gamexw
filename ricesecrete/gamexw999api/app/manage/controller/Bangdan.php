<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\BangdanModel;
use app\manage\factory\BangdanFactory;
use think\facade\Request;
use think\facade\View;

class Bangdan
{
    protected $middleware = ['ManageCheckLogin', "Language"];
    //列表
    public function list(\think\Request $request)
    {
        $rsps = (new BangdanFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if ($info['start_date'] == '' || $info['end_date'] == '') {
                return Respons::run(0, [], lang('请选择日期'));
            }
            $start_date = $info['start_date'];
            $end_date = $info['end_date'];
            $info['add_time'] = time();
            $info['issue_number'] = date("Ymd", strtotime($info['start_date'])) . date("md", strtotime($info['end_date']));
            $info['start_date'] = strtotime($info['start_date']);
            $info['end_date'] = strtotime($info['end_date']);
            $info['f_a_arr'] = json_encode($info['f_a_arr']);
            $BangdanModel = new BangdanModel();
            $res = $BangdanModel->insertGetId($info);
            $info['start_date'] = $start_date;
            $info['end_date'] = $end_date;
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, $BangdanModel, lang('榜单添加'));
        }
        return View::fetch('bangdan/add');
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $BangdanModel = new BangdanModel();
            $start_date = $info['start_date'];
            $end_date = $info['end_date'];
            $info['issue_number'] = date("Ymd", strtotime($info['start_date'])) . date("md", strtotime($info['end_date']));
            $info['start_date'] = strtotime($info['start_date']);
            $info['end_date'] = strtotime($info['end_date']);
            $info['f_a_arr'] = json_encode($info['f_a_arr']);
            $data = $BangdanModel->where('id', $info['id'])->field("*,from_unixtime(start_date,'%Y-%m-%d %H:%i:%s') as start_date,from_unixtime(end_date,'%Y-%m-%d %H:%i:%s') as end_date")->find();
            $res = $BangdanModel->update($info);
            $info['start_date'] = $start_date;
            $info['end_date'] = $end_date;
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $BangdanModel, lang('榜单修改'));

        }
        if (isset($param['find'])) {
            $BangdanModel = new BangdanModel();
            $res = $BangdanModel->where("id", $param['id'])->find();
            $res['start_date'] = date("Y-m-d H:i:s", $res['start_date']);
            $res['end_date'] = date("Y-m-d H:i:s", $res['end_date']);
            $res['f_a_arr'] = json_decode($res['f_a_arr'], true);
            return Respons::run(1, $res);
        }
        if (isset($param['clear'])) {
            $ApiRedis = new ApiRedis();
            for ($type = 1; $type <= 3; $type++) {
                for ($i = 10; $i <= 50; $i++) {
                    $redis_key = md5("generateVirtualData-v3{$type}:" . $i);
                    $redis_type_key = md5("generateVirtualData{$type}");
                    $ApiRedis->delete($redis_key);
                    $ApiRedis->delete($redis_type_key);
                    $ApiRedis->delete("game_order4" . $i);
                }
            }
            return Respons::run(1);
        }
        View::assign("id", $param['id']);
        return View::fetch('bangdan/edit');
    }

    //编辑
    public function status_edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $BangdanModel = new BangdanModel();
            $res = $BangdanModel->update($info);
            return Respons::run($res ? 1 : 0);
        }
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $BangdanModel = new BangdanModel();
            $res = $BangdanModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $BangdanModel, lang('榜单删除'));

        }
    }
}