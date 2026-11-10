<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\ActionModel;
use app\common\model\ArtificialIntegralModel;
use app\common\model\IntegralMxModel;
use app\common\model\LotteryConfigModel;
use app\common\model\LotteryPrizeModel;
use app\common\model\LotteryRecordModel;
use app\common\model\NewsModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserModel;
use app\common\Utils;
use app\manage\factory\ActionFactory;
use app\model\ThirdConfigs;
use think\facade\Db;
use think\facade\Session;
use think\Request;

class Action
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $t_apply_start_end_time = explode(" - ", $info['t_apply_start_end_time']);
            $t_show_start_end_time = explode(" - ", $info['t_show_start_end_time']);
            if (!isset($t_apply_start_end_time[1]) && !isset($t_apply_start_end_time[1])) {
                return Respons::run(0, [], lang("请选择时间"));
            }
            $info['base_show_level'] = $info['base_show_level'] == 0 ? 'all' : $info['base_show_level'];
            $info['zg_level'] = $info['zg_level'] == 0 ? 'all' : $info['zg_level'];
            $info['t_apply_start_time'] = strtotime($t_apply_start_end_time[0]);
            $info['t_apply_end_time'] = strtotime($t_apply_start_end_time[1]);
            $info['t_show_start_time'] = strtotime($t_show_start_end_time[0]);
            $info['t_show_end_time'] = strtotime($t_show_start_end_time[1]);
            $info['dis_code'] = ($info['base_type'] == 1 || $info['base_type'] == 2 ? 'CK' : ($info['base_type'] == 3 ? 'YB' : ($info['base_type'] == 6 ? 'JY' : 'TYJ'))) . date("YmdHi");
            $info['add_time'] = time();
            //判断流水类型
            if (isset($info['water_type'])) {
                if ($info['water_type'] == 1) {
                    //指定平台
                    if (!isset($info['platform_ids']) || !$info['platform_ids']) {
                        return Respons::run(0, [], '请选择流水对应平台');
                    }
                    $info['water_type_ids'] = implode(',', ThirdConfigs::where('tc_name', 'in', $info['platform_ids'])->column('tc_id'));
                } elseif ($info['water_type'] == 2) {
                    //指定游戏
                    if (!isset($info['game_ids']) || !$info['game_ids']) {
                        return Respons::run(0, [], '请选择流水对应游戏');
                    }
                    $info['water_type_ids'] = $info['game_ids'];
                }
            }
            unset($info['t_apply_start_end_time']);
            unset($info['t_show_start_end_time']);
            unset($info['game_ids']);
            unset($info['platform_ids']);
            // 首充活动和累计充值活动强制用户领取次数为1
            if ($info['base_type'] == 1 || $info['zg_cz_type'] == 1) {
                $info['base_apply_dr_max'] = 1;
            }
            try {
                if ($info['base_type'] == 1 || $info['base_type'] == 2) {
                    validate(\app\common\validate\ActionValidate::class)->scene("1")->check($info);
                } else if ($info['base_type'] == 3) {
                    validate(\app\common\validate\ActionValidate::class)->scene("3")->check($info);
                } else if ($info['base_type'] == 4) {
                    validate(\app\common\validate\ActionValidate::class)->scene("4")->check($info);
                } else if ($info['base_type'] == 5) {
                } else if ($info['base_type'] == 6) {
                    $info['base_apply_number_max'] = 1;
                    $info['base_apply_dr_max'] = 1;
                    $info['everyday_person_num'] = 1;
                    validate(\app\common\validate\ActionValidate::class)->scene("6")->check($info);
                } else if ($info['base_type'] == 7) {
                    if (isset($info['month_activity']) && count($info['month_activity'])) {
                        foreach ($info['month_activity'] as $item) {
                            validate(\app\common\validate\ActionValidate::class)->scene("7")->check($item);
                        }
                    }
                } else {
                    return Respons::run(0, [], lang("类型错误"));
                }
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if (!empty($info['game_type'])) {
                foreach ($info['game_type'] as &$v) {
                    $v = intval($v);
                }
            }
            $info['reg_json'] = json_encode($info['reg_json'], true);
            $info['month_activity'] = json_encode($info['month_activity'], true);
            $info['game_type'] = json_encode($info['game_type'], true);
            $info['level_json'] = json_encode($info['level_json'], true);
            $res = ActionModel::insertGetId($info);
            $param['info']['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $param['info'], new ActionModel(), lang('优惠添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $t_apply_start_end_time = explode(" - ", $info['t_apply_start_end_time']);
            $t_show_start_end_time = explode(" - ", $info['t_show_start_end_time']);
            if (!isset($t_apply_start_end_time[1]) && !isset($t_apply_start_end_time[1])) {
                return Respons::run(0, [], lang("请选择时间"));
            }
            $info['base_show_level'] = $info['base_show_level'] == 0 ? 'all' : $info['base_show_level'];
            $info['zg_level'] = $info['zg_level'] == 0 ? 'all' : $info['zg_level'];
            $info['t_apply_start_time'] = strtotime($t_apply_start_end_time[0]);
            $info['t_apply_end_time'] = strtotime($t_apply_start_end_time[1]);
            $info['t_show_start_time'] = strtotime($t_show_start_end_time[0]);
            $info['t_show_end_time'] = strtotime($t_show_start_end_time[1]);
//            $info['dis_code']=($info['base_type']==1 || $info['base_type']==2?'CK':($info['base_type']==3?'YB':'TYJ')).date("YmdHi");
            $info['add_time'] = time();
            //判断流水类型
            if (isset($info['water_type'])) {
                if ($info['water_type'] == 1) {
                    //指定平台
                    if (!isset($info['platform_ids']) || !$info['platform_ids']) {
                        return Respons::run(0, [], '请选择流水对应平台');
                    }
                    $info['water_type_ids'] = implode(',', ThirdConfigs::where('tc_name', 'in', $info['platform_ids'])->column('tc_id'));
                } elseif ($info['water_type'] == 2) {
                    //指定游戏
                    if (!isset($info['game_ids']) || !$info['game_ids']) {
                        return Respons::run(0, [], '请选择流水对应游戏');
                    }
                    $info['water_type_ids'] = $info['game_ids'];
                }
            }
            unset($info['t_apply_start_end_time']);
            unset($info['t_show_start_end_time']);
            unset($info['platform_ids']);
            unset($info['game_ids']);
            // 首充活动和累计充值活动强制用户领取次数为1
            if ($info['base_type'] == 1 || $info['zg_cz_type'] == 1) {
                $info['base_apply_dr_max'] = 1;
            }


            try {
                if ($info['base_type'] == 1 || $info['base_type'] == 2) {
                    validate(\app\common\validate\ActionValidate::class)->scene("1")->check($info);
                } else if ($info['base_type'] == 3) {
                    validate(\app\common\validate\ActionValidate::class)->scene("3")->check($info);
                } else if ($info['base_type'] == 4) {
                    validate(\app\common\validate\ActionValidate::class)->scene("4")->check($info);
                } else if ($info['base_type'] == 5) {
                } else if ($info['base_type'] == 6) {
                    $info['base_apply_number_max'] = 1;
                    $info['base_apply_dr_max'] = 1;
                    $info['everyday_person_num'] = 1;
                    validate(\app\common\validate\ActionValidate::class)->scene("6")->check($info);
                } else if ($info['base_type'] == 7) {
                    if (isset($info['month_activity']) && count($info['month_activity'])) {
                        foreach ($info['month_activity'] as $item) {
                            validate(\app\common\validate\ActionValidate::class)->scene("7")->check($item);
                        }
                    }
                } else {
                    return Respons::run(0, [], lang("类型错误"));
                }
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if (!empty($info['game_type'])) {
                foreach ($info['game_type'] as &$v) {
                    $v = intval($v);
                }
            }
            $info['reg_json'] = json_encode($info['reg_json'], true);
            $info['month_activity'] = json_encode($info['month_activity'], true);
            $info['game_type'] = json_encode($info['game_type'], true);
            $info['level_json'] = json_encode($info['level_json'], true);
            $ActionModel = new ActionModel();
            $action = $ActionModel->where("id", $info['id'])->find();
            if ($action['base_type'] != $info['base_type']) {
                return Respons::run(0, [], lang("无法修改活动类型"));
            }
            if ($info['zg_cz_type'] != $action['zg_cz_type']) {
                return Respons::run(0, [], lang("充值要求不能修改"));
            }
            if ($info['base_type'] != 6) {
                //如果不是救援金  无法修改日期类型
                if ($action['date_type'] != $info['date_type']) {
                    return Respons::run(0, [], lang("无法修改日期类型"));
                }
            }
            $res = $ActionModel->update($info);
            $action['t_apply_start_end_time'] = date("Y-m-d H:i:s", $action['t_apply_start_time']) . " - " . date("Y-m-d H:i:s", $action['t_apply_end_time']);
            $action['t_show_start_end_time'] = date("Y-m-d H:i:s", $action['t_show_start_time']) . " - " . date("Y-m-d H:i:s", $action['t_show_end_time']);
            return Respons::run($res ? 1 : 0, '', '', $action, $param['info'], $ActionModel, lang('优惠修改'));
        }
        if (isset($param['find'])) {
            $ActionModel = new ActionModel();
            $res = $ActionModel->where("id", $param['id'])->find();
            if ($res) {
                $res = $res->toArray();
            }
            $res['t_apply_start_end_time'] = date("Y-m-d H:i:s", $res['t_apply_start_time']) . " - " . date("Y-m-d H:i:s", $res['t_apply_end_time']);
            $res['t_show_start_end_time'] = date("Y-m-d H:i:s", $res['t_show_start_time']) . " - " . date("Y-m-d H:i:s", $res['t_show_end_time']);
            $res['reg_json'] = json_decode($res['reg_json'], true);
            $res['month_activity'] = json_decode($res['month_activity'], true);
            $res['level_json'] = isset($res['level_json']) ? json_decode($res['level_json'], true) : '';
            $res['game_type'] = isset($res['game_type']) ? json_decode($res['game_type'], true) : '';
            $res['action'] = Utils::getOptions('action', ['id', 'dis_code'], [['id', "<>", $param['id']]]);
            //根据流水类型获取平台ids和游戏ids
            $res['platform_ids'] = [];
            $res['game_ids'] = '';
            if ($res['water_type'] == 1) {
                //指定平台
                $res['platform_ids'] = ThirdConfigs::where('tc_id', 'in', $res['water_type_ids'])->column('tc_name');
            } elseif ($res['water_type'] == 2) {
                //指定游戏
                $res['game_ids'] = $res['water_type_ids'];
            }
            unset($res['water_type_ids']);
            array_unshift($res['action'], ['label' => "无", 'value' => 0]);
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = ActionModel::where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', new ActionModel(), lang('优惠删除'));
        }
    }

    public function lottery_config_find(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->lottery_config_find();
        return $rsps;
    }

    //抽奖配置
    public function lottery_config(Request $request)
    {
        $param = $request->param();
        $LotteryConfigModel = new LotteryConfigModel();
        if (isset($param['save'])) {
            $info = $param['info'];
            $info['starttime'] = 0;
            $info['endtime'] = 0;
            $atime = $etime2 = '';
            if (!empty($info['time'])) {
                $time = explode(" - ", $info['time']);
                $atime = $time[0];
                $etime2 = $time[1];
                $info['starttime'] = strtotime($time[0]);
                $info['endtime'] = strtotime($time[1]);
            }
            unset($info['time']);
            $data = $LotteryConfigModel->where('id', 1)->find();
            $info['id'] = 1;
            $res = $LotteryConfigModel->update($info);
            $data['starttime'] = empty($data['starttime']) ? '' : date('Y-m-d H:i:s', $data['starttime']);
            $data['endtime'] = empty($data['endtime']) ? '' : date('Y-m-d H:i:s', $data['endtime']);
            $info['starttime'] = $atime;
            $info['endtime'] = $etime2;
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $LotteryConfigModel, lang('抽奖配置操作'));
        }

        if (isset($param['find'])) {

        }
    }

    //奖品列表
    public function lottery_prize(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->lottery_prize();
        return $rsps;
    }

    //奖品删除
    public function lottery_prize_delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = LotteryPrizeModel::where("id", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', new LotteryPrizeModel(), lang('奖品删除'));
        }
    }

    //奖品编辑/添加
    public function lottery_prize_save(Request $request)
    {
        $param = $request->param();
        $model = new LotteryPrizeModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $id = isset($info['id']) ? $info['id'] : 0;
            $data = $model->where('id', $id)->find();
            if (empty($info['lottery_id'])) {
                return Respons::run(0, '', lang('请选着转盘'));
            }
            if (empty($info['title'])) {
                return Respons::run(0, '', lang('请输入名称'));
            }
            if (!is_numeric($info['probability'])) {
                return Respons::run(0, '', lang('请输入数字类型'));
            }
            $map = [];
            $map[] = ['id', '<>', $info['id']];
            $map[] = ['lottery_id', '=', $info['lottery_id']];
            $probability = round(100 - $model->where($map)->sum('probability'), 2);
            if ($info['probability'] > $probability) {
                return Respons::run(0, '', lang('奖品综合中奖几率不能超过') . '100%');
            }
            if ($id > 0) {
                $res = $model->update($info);
            } else {
                $info['pic'] = toMedia($info['pic']);
                $res = $model->insertGetId($info);
                $info['id'] = $res;
            }
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $model, lang('奖品设置'));
        }
        $id = isset($param['id']) ? $param['id'] : 0;
        if (isset($param['find'])) {
            $find = $model->where('id', $id)->find();
            return Respons::run(1, $find);
        }
    }


    //抽奖记录
    public function lottery_record(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->lottery_record();
        return $rsps;
    }

    //抽奖记录
    public function lottery_condition_record(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->lottery_condition_record();
        return $rsps;
    }

    //添加假抽奖记录
    public function lottery_fakerecord(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];

            Db::startTrans();
            try {
                $uids = explode(",", $info['username']);
                $prize = LotteryPrizeModel::where('id', $info['prize_id'])->find();
                $Config = LotteryConfigModel::where('id', 1)->find();
                if (empty($prize)) {
                    return Respons::run(0, '', lang("奖品不存在"));
                }
                $da = [];
                foreach ($uids as $val) {
                    $user = UserModel::where("username", $val)->find();
                    if (!isset($user['id'])) {
                        return Respons::run(0, '', lang("用户不存在"));
                    }
                    $add['uid'] = $user['id'];
                    $add['prize_id'] = $info['prize_id'];
                    $add['lottery_id'] = $info['lottery_id'];
                    $add['title'] = $prize['title'];
                    $add['time'] = time();
                    $add['prize_type'] = $prize['type'];
                    $add['price'] = $info['is_pay'] == '1' ? $Config['required_balance'] : 0;
                    $add['is_free'] = 0;
                    $add['prize_price'] = $prize['amount'];
                    $add['integral'] = $info['is_pay'] == '0' ? $Config['required_integral'] : 0;
                    $add['is_fake'] = 1;
                    $res = LotteryRecordModel::insertGetId($add);
                    $da['id'] = $res;
                    $da['uid'] = $user['id'];
                    $da['prize_id'] = $info['prize_id'];
                    $da['lottery_id'] = $info['lottery_id'];
                    $da['title'] = $prize['title'];
                    $da['time'] = time();
                    $da['prize_type'] = $prize['type'];
                    $da['price'] = $info['is_pay'] == '1' ? $Config['required_balance'] : 0;
                    $da['is_free'] = 0;
                    $da['prize_price'] = $prize['amount'];
                    $da['integral'] = $info['is_pay'] == '0' ? $Config['required_integral'] : 0;
                    $da['is_fake'] = 1;
                    Respons::run(1, '', '', '', $da, new LotteryRecordModel(), lang('添加假抽奖数据'));
                }
                // 提交事务
                Db::commit();
                return Respons::run(1);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    //积分记录
    public function integral_record(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->integral_record();
        return $rsps;
    }

    //积分记录导出
    public function integral_record_excel(Request $request)
    {
        $param = $request->param();
        $accurate = isset($param['accurate']) ? $param['accurate'] : 0;
        $where = [];
        if (isset($param['username']) && $param['username'] != '' && $accurate == 0) {
            $where[] = ["c.username", "like", "%" . $param['username'] . "%"];
        }

        if (isset($param['username']) && $param['username'] != '' && $accurate == 1) {
            $where[] = ["c.username", "=", $param['username']];
        }

        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($time[0])];
            $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        if (isset($param['lebal']) && $param['lebal'] != '') {
            $where[] = ["c.lebal", "=", $param['lebal']];
        }

        if (isset($param['uid']) && $param['uid'] > 0) {
            $where[] = ['a.uid', '=', $param['uid']];
        }

        if (isset($param['type']) && $param['type'] > 0) {
            $where[] = ['a.type', '=', $param['type']];
        }

        $list = ArtificialIntegralModel::alias("a")
            ->join("dq_user c", "a.uid=c.id")
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,c.username")
            ->select();
        $data = [];
        foreach ($list as $v) {
            $v['add_time'] = date("Y-m-d H:i:s", $v['add_time']);
            $v['msg'] = lang($v['msg']);
            $v['lebal'] = $v['lebal'] == 1 ? lang('普通用户') : lang('测试用户');
            $v['type'] = $v['type'] == 1 ? lang('积分增加') : lang('积分减少');
            $data[] = [
                $v['username'], $v['lebal'], $v['type'], $v['integral'], $v['old_integral'], $v['new_integral'], $v['msg'], $v['admin_name'], $v['add_time'],
            ];
        }
        $header = [
            lang('用户名'), lang('用户标签'), lang('类型'), lang('调整积分'), lang('调整前'), lang('调整后'), lang('明细备注'), lang('操作人'), lang('操作时间')
        ];

        Excel($header, $data, lang('积分记录数据') . date('Ymd', time()));
    }

    //积分调整
    public function integral_save(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if ($info['username'] == '' || empty($info['username'])) {
                return Respons::run(0, [], lang('请选择用户'));
            }

            if ($info['integral'] <= 0 || $info['integral'] == '') {
                return Respons::run(0, [], lang('请输入调整积分'));
            }
            Db::startTrans();
            try {
                $uids = explode(",", $info['username']);
                foreach ($uids as $val) {
                    $user = UserModel::where("username", $val)->find();
                    if (!isset($user['id'])) {
                        throw new \Exception(lang("用户不存在"), 1);
                    }
                    if (!isset($info['mx_msg'])) $info['mx_msg'] = '';
                    if ($info['type'] == 1) {
                        $mx_msg = $info['mx_msg'] == '' ? "积分增加" : $info['mx_msg'];
                        $mx = IntegralMxModel::cz_mx($user['id'], 1, $info['integral'], $mx_msg);
                    } elseif ($info['type'] == 2) {
                        $mx_msg = $info['mx_msg'] == '' ? "积分减少" : $info['mx_msg'];
                        $mx = IntegralMxModel::cz_mx($user['id'], 2, $info['integral'], $mx_msg);
                    }

                    $add['uid'] = $user['id'];
                    $add['lebal'] = $user['lebal'];
                    $add['admin_name'] = Session::get("adminInfo.name");
                    $add['type'] = $info['type'];
                    $add['integral'] = $info['integral'];
                    $add['old_integral'] = $mx['old_integral'];
                    $add['new_integral'] = $mx['new_integral'];
                    $add['msg'] = $mx_msg;
                    $add['add_time'] = time();
                    ArtificialIntegralModel::insert($add);
                    UserAdminLogModel::add_log($user['id'], '人工调整积分, 调整积分: ' . $info['integral'], Session::get("adminInfo.name"));
                }
                // 提交事务
                Db::commit();
                return Respons::run(1, '', '', '', $info, new ArtificialIntegralModel(), lang('积分调整'));
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    //抽奖报表
    public function lottery_data_record(\think\Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->lottery_data_record();
        return $rsps;
    }

    /**
     * 导入
     * @return void
     */
    public function import(Request $request)
    {
        $data = json_decode(base64_decode($request->param('data/s')), true);
        if (empty($data)) {
            return Respons::run(0, ['data' => $data], '数据有误');
        }
        unset($data['action']['id']);
        $data['action']['add_time'] = time();
        $action_id = ActionModel::insertGetId($data['action']);
        if (!empty($data['news'])) {
            foreach ($data['news'] as &$v) {
                unset($v['id']);
                $v['action_id'] = $action_id;
                $v['add_time'] = time();
            }
            NewsModel::insertAll($data['news']);
        }
        Utils::addOptLog(null, $data, 'action', '导入优惠活动', new ActionModel());
        return Respons::run(1, '', '操作成功');
    }

    /**
     * 导出
     * @return void
     */
    public function export(Request $request)
    {
        $id = $request->param('id/d');
        $info = ActionModel::where('id', $id)->find();
        if (empty($info)) {
            return Respons::run(0, ['data' => $data], '优惠活动信息不存在');
        }
        $data['action'] = $info;
        $data['news'] = NewsModel::where(['action_id' => $info['id']])->select();
        return Respons::run(1, ['data' => base64_encode(json_encode($data)), 'json' => $info], '操作成功');
    }

}
