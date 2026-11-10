<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\RedPacketLogModel;
use app\common\model\RedPacketModel;
use app\common\model\RedPacketTimeModel;
use app\common\Utils;
use app\manage\factory\RedPacketFactory;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Request;

class RedPacket
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $redPacketModel = new RedPacketModel();
        //活动名称
        if (isset($param['name']) && $param['name'] !== '') {
            $condition[] = ['name', 'like', "%" . $param['name'] . "%"];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $condition[] = ["start_time", ">=", Utils::getQueryDateTime($time[0])];
            $condition[] = ["start_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        $model_arr = [
            1 => '每日',
            2 => '每周',
        ];
        $condition_arr = [
            0 => '不限制',
            1 => '完成首冲用户',
            2 => '当日有存款用户',
            3 => '当天/累计完成流水要求',
            4 => '手机/银行卡号/个人信息(KYC验证)'
        ];
        $paginate = $redPacketModel
            ->where($condition)
            ->field('*')
            ->order("sort asc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($paginate as &$value) {
            $value['start_time'] = $value['start_time'] ? date('Y-m-d H:i:s', $value['start_time']) : '';
            $value['end_time'] = $value['end_time'] ? date('Y-m-d H:i:s', $value['end_time']) : '';
            $value['model'] = $model_arr[intval($value['model'])];
            $value['condition_str'] = $condition_arr[intval($value['condition'])];
        }
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        $condition_temp = [];
        foreach ($condition_arr as $key => $value) {
            $condition_temp[] = [
                ['label' => intval($key), 'name' => $value],
            ];
        }
        $rsps['condition'] = $condition_temp;
        $model_arr_temp = [];
        foreach ($model_arr as $key => $value) {
            $model_arr_temp[] = [
                ['label' => intval($key), 'name' => $value],
            ];
        }
        $rsps['model'] = $model_arr_temp;
        $rsps['tags'] = Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $info['model_day'] = json_encode($info['model_day']);
            unset($info['id']);
            $res = RedPacketModel::insertGetId($info);
            if ($res) {
                //添加初始化有效时间区间数据
                (new RedPacketFactory())->intValidityTime($res,$info['model'],json_decode($info['model_day'],true));
            }
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new RedPacketModel(), lang('红包雨活动添加'));
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $redPacketModel = new RedPacketModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $redPacketModel->where('id', $info['id'])->find();
            $info['update_time'] = time();
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $info['model_day'] = json_encode($info['model_day']);
            $res = $redPacketModel->update($info);
            if ($res) {
                //添加初始化有效时间区间数据
                (new RedPacketFactory())->intValidityTime($info['id'],$info['model'],json_decode($info['model_day'],true));
            }
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $redPacketModel, lang('红包雨活动修改'));
        }
        if (isset($param['find'])) {
            $res = $redPacketModel->where("id", $param['id'])->find();
            if(!empty($res['model_day'])){
                $res['model_day'] = json_decode($res['model_day'],true);
            }else{
                $res['model_day'] = [];
            }
            $res['start_time'] = date("Y-m-d H:i:s", $res['start_time']);
            $res['end_time'] = date("Y-m-d H:i:s", $res['end_time']);
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $redPacketModel = new RedPacketModel();
            $res = $redPacketModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $redPacketModel, lang('红包雨活动删除'));
        }
    }

    //日期区间天数列表
    public function dayList(Request $request){
        $rid = $request->param('rid');
        $redP = RedPacketModel::where('id',$rid)->field('model')->find();
        $map = [
            'rid'=>$rid,
            'model'=>$redP['model']
        ];
        $list = RedPacketTimeModel::where($map)->order('week ASC')->select();
        $weekDay = array_flip(RedPacketFactory::$weekDay);
        $list_arr = [];
        foreach ($list as $value){
            if(!empty($value['validity_time'])){
                $value['validity_time'] = json_decode($value['validity_time'],true);
                $value['validity_time_str'] = implode("---",$value['validity_time']);
            }else{
                $value['validity_time_str'] = "";
            }
            if(empty($value['week'])){
                $value['week_str'] = "不限";
            }else{
                $value['week_str'] = $weekDay[$value['week']];
            }

            $list_arr[] = $value;
        }
        $rsps['data'] = [
            'data'=>$list_arr,
            'total'=>count($list_arr)
        ];
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //区间日期修改
    public function dayEdit(Request $request){
        $param = $request->param();
        $redPacketTimeModel = new RedPacketTimeModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            try {
                if(!empty($info['validity_time_str'])){
                    $info['validity_time_str'] = json_decode($info['validity_time_str'],true);
                }else{
                    $info['validity_time_str'] = [];
                }
                $data = (new RedPacketFactory())->addValidityTime($info['rid'], $info['validity_time_str'], $info['model'], $info['week']);
            }catch (DbException $e) {
                return Respons::run(0);
            }
            return Respons::run($data['code']==1 ? 1 : 0, '', $data['msg']?$data['msg']:'', $data, $info, $redPacketTimeModel, lang('红包雨活动修改'));
        }
        if (isset($param['find'])) {
            $res = $redPacketTimeModel->where("id", $param['id'])->find();
            if(!empty($res['validity_time'])){
                $res['validity_time_str'] = $res['validity_time'];
                $res['validity_time'] = json_decode($res['validity_time'],true);
            }else{
                $res['validity_time_str'] = '[]';
                $res['validity_time'] = [];
            }
            return Respons::run(1, $res);
        }
    }

    /**
     * 红包领取记录
     * @param Request $request
     * @return \think\response\Json
     */
    public function awardLists(Request $request)
    {
        $param = $request->param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $redPacketLogModel = new RedPacketLogModel();
        //活动id
        if (isset($param['rid']) && $param['rid'] !== '') {
            $condition[] = ['r.rid', '=', $param['rid']];
        }
        if (isset($param['uid']) && $param['uid'] !== '') {
            $condition[] = ['r.uid', '=', $param['uid']];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $condition[] = ["r.create_time", ">=", Utils::getQueryDateTime($time[0])];
            $condition[] = ["r.create_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        if (isset($param['export']) && $param['export'] == 1) {
            return (new RedPacketFactory($request->token->uid))->exportList($condition);
        }
        $paginate = $redPacketLogModel
            ->alias('r')
            ->join('red_packet a', 'a.id=r.rid')
            ->where($condition)
            ->field('r.*,a.name as r_name')
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}