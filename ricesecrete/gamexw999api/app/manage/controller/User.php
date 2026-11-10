<?php

namespace app\manage\controller;

use app\common\lib\EmailCode;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\MailNotifyTemplateModel as MailNotifyTemplateModel;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use app\common\model\UserAddressModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserGoogleModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserModel;
use app\common\model\UserLevelModel;
use app\common\model\UserCkModel;
use app\common\model\UserLoginModel;
use app\common\model\UserOfflineModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserAgentModel;
use app\common\model\UserBalanceMxModel;
use app\common\model\UserAdminLogModel;
use app\common\model\ActionApplyModel;
use app\common\model\ActionModel;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\UserOnlineModel;
use app\common\model\UserWaterModel;
use app\common\service\MemberService;
use app\common\Utils;
use app\common\lib\Uploads;
use app\common\model\GameBetsModel;
use app\common\model\UserWithdrawalModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\facade\Queue;
use think\facade\View;
use think\facade\Db;
use think\facade\Session;
use app\manage\factory\UserFactory;
use think\Request;

class User
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list(Request $request)
    {
        $rsps = (new UserFactory($request->token))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $rsps = (new UserFactory($request->token))->add();
        return $rsps;
    }

    public function edit(Request $request)
    {
        $rsps = (new UserFactory($request->token))->edit();
        return $rsps;
    }

    public function status(Request $request)
    {
        $rsps = (new UserFactory($request->token))->status();
        return $rsps;
    }

    public function edit_detail(Request $request)
    {
        $rsps = (new UserFactory($request->token))->edit_detail();
        return $rsps;
    }

    public function detail(Request $request)
    {
        $rsps = (new UserFactory($request->token))->detail();
        return $rsps;
    }

    //会员导出
    public function excel(Request $request)
    {
        $param = $request->param();
        $where = [];
        $UserModel = new UserModel();
        $ApiRedis = new ApiRedis();
        if (isset($param['username']) && $param['username'] != '') {
            if (is_numeric($param['username'])) {
                $where[] = ["a.id|a.rec_code|a.username", "=", $param['username']];
            } else {
                $where[] = ["a.rec_code|a.username", "=", $param['username']];
            }
        }
        if (isset($param['rec_id']) && $param['rec_id'] != '') {
            //查询rec_id
            $sqlwhere[] = ["id|rec_code|username", "=", $param['rec_id']];
            $rec_tree = UserModel::where($sqlwhere)->field("id")->value('tree');
            if (!empty($rec_tree)) {
                $where[] = ["a.tree", "like", $rec_tree . "%"];
            }
        }

        if (isset($param['lebals']) && $param['lebals'] != '') {
            $where[] = ["a.lebal", "=", $param['lebals']];
        }
        if (isset($param['end_login_ip']) && $param['end_login_ip'] != '') {
            $where[] = ["a.end_login_ip|a.reg_ip", "like", "%" . $param['end_login_ip'] . "%"];
        }
        if (isset($param['name']) && $param['name'] != '') {
            $where[] = ["a.username|a.mail|a.phone", "like", "%" . $param['name'] . "%"];
        }
        if (isset($param['level']) && $param['level'] != '') {
            $where[] = ["a.level_id", "=", $param['level']];
        }
        if (isset($param['login_time']) && $param['login_time'] != '') {
            $date = explode(' - ', $param['login_time']);
            $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
        }
        $where1 = '';
        if (isset($param['ip']) && $param['ip'] > 0) {
            $sql = UserLoginModel::where(['login_ip' => $param['ip']])->field('uid')->buildSql();
            $where1 = "a.id in(" . $sql . ")";
        }

        //把用户等级缓存
        $UserLevelModel = new UserLevelModel();
        $user_level_data = $UserLevelModel->column('level_name', 'id');

        $list = $UserModel
            ->alias("a")
            ->where($where)
            ->where($where1)
            ->field("a.id,a.username,a.mail,a.lebal,a.rec_code,a.add_time,a.login_time,a.balance,a.phone,a.qh,"
                . "a.set_fund_money,a.rec_id,a.live_fund_money,a.integral,a.level_id,a.status,a.end_login_ip,a.reg_ip,"
                . "(select username from dq_user where id=a.rec_id ) as rec_name")
            ->select();

        foreach ($list as $value) {
            $value['level_name'] = $user_level_data[$value['level_id']];
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
            $value['login_time'] = date("Y-m-d H:i:s", $value['login_time']);
            $value['lebal'] = $value['lebal'] == 1 ? lang('普通用户') : lang('测试用户');
            $value['status'] = $value['status'] == 1 ? lang('启用') : lang('禁用');
            $data[] = [
                $value['username'], $value['mail'], $value['lebal'], $value['rec_code'], $value['rec_name'], $value['level_name'], $value['add_time'], $value['reg_ip'],
                $value['login_time'], $value['end_login_ip'], $value['balance'], $value['status'], $value['phone'] . ' ', $value['qh'] . ' '
            ];
        }

        $header = [
            lang('用户名称'), lang('邮箱'), lang('用户标签'), lang('推广码'), lang('推广用户名'), lang('会员等级'), lang('注册时间'),
            lang('注册ip'), lang('登录时间'), lang('登录ip'), lang('余额'), lang('状态'), lang('手机号'), lang('区号')
        ];

        genCsv($header, $data, lang('会员管理数据') . date('Ymd', time()));
        Respons::run(1, '', '', '', '', $UserModel, lang('会员管理数据导出'));
    }

    public function line(Request $request)
    {
        $param = $request->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $uids = getOnlineUids();
        if (!empty($uids)) {
            $where = [
                ["a.id", "in", $uids],
            ];

            $where1 = '';
            if (isset($param['username']) && $param['username'] != '') {
                $where[] = ["a.id|a.rec_code|a.username", "=", $param['username']];
            }
            if (isset($param['lebals']) && $param['lebals'] != '') {
                $where[] = ["a.lebal", "=", $param['lebals']];
            }
            if (isset($param['rec_id']) && $param['rec_id'] != '') {
                $where[] = ["c.id|c.rec_code", "=", $param['rec_id']];
            }
            if (isset($param['end_login_ip']) && $param['end_login_ip'] != '') {
                $where[] = ["a.end_login_ip|a.reg_ip", "like", "%" . $param['end_login_ip'] . "%"];
            }
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ["a.username|a.mail|a.phone", "like", "%" . $param['name'] . "%"];
            }

            if (isset($param['rec_id']) && $param['rec_id'] != '') {
                $where[] = ["c.id|c.rec_code", "=", $param['rec_id']];
            }

            if (isset($param['ip']) && $param['ip'] > 0) {
                $sql = UserLoginModel::where(['login_ip' => $param['ip']])->field('uid')->buildSql();
                $where1 = "a.id in(" . $sql . ")";
            }
            if (isset($param['login_time']) && $param['login_time'] != '') {
                $date = explode(' - ', $param['login_time']);
                $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
                $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            }
            $UserModel = new UserModel();
            $rsps['data'] = $UserModel
                ->alias("a")
                ->Join("dq_user_level b", "a.level_id = b.id", 'LEFT')
                ->Join("dq_user c", "a.rec_id = c.id", 'LEFT')
                ->where($where)
                ->where($where1)
                ->order("a.id desc")
                ->field("a.*,b.level_name,c.username as rec_name")
                ->paginate($limit, false, ['query' => $param]);
            foreach ($rsps['data'] as &$value) {
                $wh = [];
                $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                $value['login_time'] = date("Y-m-d H:i:s", $value['login_time']);
                $wh[] = ['login_ip', 'in', [$value['end_login_ip'], $value['reg_ip']]];
                $wh[] = ['uid', 'not in', [$value['id']]];
                $value['login_rep'] = UserLoginModel::where($wh)->value('id') ? 1 : 0;
                $value['count_pnumber'] = $UserModel->where('rec_id', $value['id'])->value('id');
            }
        } else {
            $rsps['data'] = [];
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //编辑
    public function status_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $UserModel = new UserModel();
            $data = UserModel::where('id', $info['id'])->field('id,status,usertokenkey')->find();
            $res = $UserModel->update($info);
            //设置会员登出
            if ($res && $info['status'] != '1') {
                $ApiRedis = new ApiRedis();
                $ApiRedis->set($data['usertokenkey'], 'loginout', 600);
            }
            UserAdminLogModel::add_log($info['id'], lang("会员状态修改"), Session::get("adminInfo.name"));
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $UserModel, lang('会员状态编辑'));
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $id = $param['id'];
        $UserModel = new UserModel();
        $res = $UserModel->where("id", "in", $id)->delete();
        return Respons::run($res ? 1 : 0, '', '', $param, '', $UserModel, lang('会员删除'));
    }

    public function get_top_user(Request $request)
    {
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $limit = $param['limit'];
        $tree = UserModel::where(['id' => $param['id']])->value('tree');
        $tree1 = explode("/", $tree);
        $new_tree = array_filter($tree1);
        $data = UserModel::where(['id' => $new_tree])->paginate($limit, false, ['query' => $param]);

        foreach ($data as $k => &$v) {
            $v['top_name'] = UserModel::where(['id' => $v['rec_id']])->value('username');
            $v['add_time'] = date("Y-m-d H:i:s", $v['add_time']);
            $v['login_time'] = date("Y-m-d H:i:s", $v['login_time']);
        }
        return Respons::run('1', $data);
    }

    public function import()
    {
        if (!empty($_FILES)) {
            $files = Uploads::upload_excel();
            if ($files['code'] == 0) {
                return Respons::run($files);
            }
            $file = $files['data']['file'];
            $objReader = IOFactory::createReader('Xlsx');
            /* @var UserLevelModel $LevelModel */
            $UserLevelModel = app()->make(UserLevelModel::class);
            $level_arr = [];
            $UserLevel = $UserLevelModel
                ->filter(function ($data) use (&$level_arr) {
                    $level_arr[$data['level_name']] = $data['id'];
                })
                ->field('id,level_name')
                ->select();
            // 启动事务
            Db::startTrans();
            try {
                $objPHPExcel = $objReader->load($file);
                $sheet = $objPHPExcel->getSheet(0);
                $highestRow = $sheet->getHighestRow();
                $items = [];
                for ($i = 2; $i <= $highestRow; $i++) {
                    if (!(string)$objPHPExcel->getActiveSheet()->getCell("A" . $i)->getValue()) {
                        return Respons::run(0, [], lang('导入数据有误,请检查后再次导入'));
                    }
                    $level_name = $objPHPExcel->getActiveSheet()->getCell("H" . $i)->getValue();
                    $items[$i] = [
                        'username' => (string)$objPHPExcel->getActiveSheet()->getCell("A" . $i)->getValue(),
                        'password' => Util::userMd5Password($objPHPExcel->getActiveSheet()->getCell("B" . $i)->getValue()),
                        'lebal' => $objPHPExcel->getActiveSheet()->getCell("C" . $i)->getValue(),
                        'rec_id' => $objPHPExcel->getActiveSheet()->getCell("D" . $i)->getValue(),
                        'balance' => $objPHPExcel->getActiveSheet()->getCell("E" . $i)->getValue(),
                        'qh' => $objPHPExcel->getActiveSheet()->getCell("F" . $i)->getValue(),
                        'phone' => $objPHPExcel->getActiveSheet()->getCell("G" . $i)->getValue(),
                        'level_id' => $level_arr[$level_name] ?? 1,
                    ];

                }
                $ids = [];
                foreach ($items as $key => $val) {
                    $tree = "";
                    $top = UserModel::where("id", $val['rec_id'])->find();
                    if (!empty($top) > 0) {
                        $tree = $top['tree'];
                        $val['rec_id'] = $val['rec_id'];
                    } else {
                        $val['rec_id'] = 0;
                    }

                    $user = UserModel::where("username", $val['username'])->find();
                    if (!isset($user['id'])) {
                        $val['add_time'] = time();
                        $val['reg_ip'] = Util::get_ip();
                        if (!empty($val['phone'])) {
                            $val['phone_status'] = 1;
                        } else {
                            $val['phone'] = '';
                        }

                        if (empty($val['qh'])) {
                            $val['qh'] = '';
                        }

                        $id = UserModel::insertGetId($val);
                        if (empty($tree)) {
                            $tree = "/" . $id . "/";
                        } else {
                            $tree = $tree . $id . "/";
                        }

                        $tree1 = explode("/", $tree);
                        $new_tree = array_filter($tree1);

                        $rec_id = isset($new_tree[count($new_tree) - 1]) ? $new_tree[count($new_tree) - 1] : 0;
                        $rec_id2 = isset($new_tree[count($new_tree) - 2]) ? $new_tree[count($new_tree) - 2] : 0;
                        $rec_id3 = isset($new_tree[count($new_tree) - 3]) ? $new_tree[count($new_tree) - 3] : 0;
                        $upd['id'] = $id;
                        $upd['tree'] = $tree;
                        $upd['rec_id'] = $rec_id;
                        $upd['rec_id2'] = $rec_id2;
                        $upd['rec_id3'] = $rec_id3;
                        $upd['rec_level'] = count($new_tree);
                        $upd['rec_code'] = Util::rec_code($id);
                        UserModel::update($upd);
                        $agent_apply_verify = Utils::sysInfo("agent_apply_verify");
                        if ($agent_apply_verify == 2) {
                            //开通微商
                            $add_agent['uid'] = $id;
                            $add_agent['apply_ip'] = Util::get_ip();
                            $add_agent['apply_time'] = time();
                            $add_agent['status'] = 3;
                            $add_agent['tree'] = $tree;
                            $add_agent['rec_id'] = $rec_id;
                            $add_agent['admin_name'] = Session::get("adminInfo.name");
                            UserAgentModel::insertGetId($add_agent);
                        }
                        $ids[] = $id;
                        UserAdminLogModel::add_log($id, lang("会员导入"), Session::get("adminInfo.name"));
                    }
                }
                Db::commit();
                if (!empty($ids)) {
                    $iddata['id'] = implode(',', $ids);
                }
                Respons::run(1, '', '', '', $iddata, new UserModel(), lang('会员数据导入'));
                return json(["code" => 1]);
            } catch (\Throwable $t) {
                Db::rollback();
                return Respons::run(0, [], $t->getMessage());
            }
        }
        return View::fetch('user/import');
    }


    public function set_limit(Request $request)
    {
        $param = $request->param();
        $wh[] = ['id', 'in', $param['ids']];
        UserModel::where($wh)->update($param['info']);
        $param['info']['ids'] = $param['ids'];
        return Respons::run(1, '', '', '', $param['info'], new UserModel(), lang('批量设置下注限额'));

    }


    //测试用户列表
    public function cs_user(Request $request)
    {
        $param = $request->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $where[] = ["a.lebal", "=", 2];
        if (isset($param['username']) && $param['username'] != '') {
            $where[] = ["a.id|a.rec_code", "=", $param['username']];
        }
        if (isset($param['rec_id']) && $param['rec_id'] != '') {
            $where[] = ["c.id|c.rec_code", "=", $param['rec_id']];
        }
        if (isset($param['end_login_ip']) && $param['end_login_ip'] != '') {
            $where[] = ["a.end_login_ip|a.reg_ip", "like", "%" . $param['end_login_ip'] . "%"];
        }
        if (isset($param['name']) && $param['name'] != '') {
            $where[] = ["a.username|a.mail|a.phone", "like", "%" . $param['name'] . "%"];
        }

        if (isset($param['login_time']) && $param['login_time'] != '') {
            $date = explode(' - ', $param['login_time']);
            $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
        }

        $UserModel = new UserModel();
        $rsps['data'] = $UserModel
            ->alias("a")
            ->Join("dq_user_level b", "a.level_id = b.id", 'LEFT')
            ->Join("dq_user c", "a.rec_id = c.id", 'LEFT')
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,b.level_name,c.username as rec_name")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$value) {
            $wh = [];
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
            $value['login_time'] = date("Y-m-d H:i:s", $value['login_time']);
            $wh[] = ['login_ip', 'in', [$value['end_login_ip'], $value['reg_ip']]];
            $wh[] = ['uid', 'not in', [$value['id']]];
            $value['login_rep'] = UserLoginModel::where($wh)->value('id') ? 1 : 0;
            $value['user_status'] = $UserModel->where('rec_id', $value['id'])->value('id') ? 1 : 0;
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //测试用户列表
    public function cs_user_delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $UserModel = new UserModel();
            $uid = empty($param['id']) ? [] : explode(',', $param['id']);
            if (session('adminInfo.role_id') != 1) {
                return Respons::run(0, [], lang('无权限操作'));
            }
            if (!empty($uid)) {
                foreach ($uid as $v) {
                    $info = $UserModel->where('rec_id', $v)->value('id');
                    if ($info) {
                        return Respons::run(1, [], lang('此用户有下级,请先删除下级再操作'));
                    }
                }
                $res = $UserModel->where("id", "in", (string)$id)->delete();
                return Respons::run($res ? 1 : 0, '', '', $param, '', $UserModel, lang('会员删除'));
            } else {
                return Respons::run(0, [], lang('请选择会员'));
            }
        }
    }

    //修改用户上级
    public function rec_edit(Request $request)
    {
        $param = $request->param();
        $info = $param['info'];
        if (!isset($info['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $UserModel = new UserModel();
        $data = UserModel::where('id', $info['id'])->field('id,tree,rec_id')->find();
        $count_pnumber = $UserModel->where('rec_id', $info['id'])->value('id');
        if (empty($count_pnumber)) {// session('adminInfo.role_id') == 1 && 
            if (!empty($info['rec_id'])) {
                $we = [['rec_code|username', '=', $info['rec_id']]];
                // 上级用户信息
                $up_user_info = UserModel::where($we)->find();
                if (empty($up_user_info)) {
                    return Respons::run(0, [], lang('上级用户不存在'));
                }
                if ($data['id'] == $up_user_info['id']) {
                    return Respons::run(0, [], lang('不能设置自己为上级'));
                }
                $upd['rec_id'] = $up_user_info['id'];
                $upd['tree'] = (!empty($up_user_info)) ? $up_user_info['tree'] . $data['id'] . '/' : $data['tree'];
            } else {
                $upd['rec_id'] = 0;
                $upd['tree'] = '/' . $info['id'] . '/';
            }

            $tree1 = explode("/", $upd['tree']);
            $new_tree = array_filter($tree1);
            $rec_id = isset($new_tree[count($new_tree) - 1]) ? $new_tree[count($new_tree) - 1] : 0;
            $rec_id2 = isset($new_tree[count($new_tree) - 2]) ? $new_tree[count($new_tree) - 2] : 0;
            $rec_id3 = isset($new_tree[count($new_tree) - 3]) ? $new_tree[count($new_tree) - 3] : 0;

            $upd['rec_id'] = $rec_id;
            $upd['rec_id2'] = $rec_id2;
            $upd['rec_id3'] = $rec_id3;
            $upd['rec_level'] = count($new_tree);

            $upd['id'] = $info['id'];
            $UserModel->update($upd);

            UserAgentModel::where('uid', $info['id'])->update([
                'rec_id' => $rec_id,
                'tree' => $upd['tree']
            ]);
            $js_data = '';
            if ($data) {
                $js_data = json_decode($data, true);
            }
            UserAdminLogModel::add_log($upd['id'], lang("会员上级修改, 上级ID[{:rec_id}]", ['rec_id' => $rec_id]), Session::get("adminInfo.name"));
            return Respons::run(1, '', '', $js_data, $upd, $UserModel, lang('会员上级修改'));
        } else {
            return Respons::run(0, [], lang('无权限操作'));
        }
        // if (isset($param['find'])) {
        //     $UserModel     = new UserModel();
        //     $res           = $UserModel->where("id", $param['id'])->field('id,rec_id')->find();
        //     $res['rec_id'] = $UserModel->where('id', $res['rec_id'])->value('username');
        //     return Respons::run(1, $res);
        // }
        // View::assign("id", $param['id']);
        // return View::fetch('user/rec_edit');
    }


    //IP统计
    public function ipstatistics(Request $request)
    {
        $param = $request->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $where = [];
        if (isset($param['login_ip']) && $param['login_ip'] != '') {
            $where[] = ["login_ip", "like", "%" . $param['login_ip'] . "%"];
        }
        $rsps['data'] = UserLoginModel::field('count(id) as login_num,login_ip')->where($where)->group('login_ip')->order("login_num desc")->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$v) {
            //登录人数
            $v['login_num'] = UserLoginModel::where(['login_ip' => $v['login_ip']])->group('uid')->count();
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //流水统计
    public function running_water()
    {
        return (new MemberService())->getUserWaterDetail();
    }

    //指定流水
    public function appointWater()
    {
        return (new MemberService())->appointWater();
    }

    //用户流水
    public function water()
    {
        return (new MemberService())->getUserWaterList();
    }

    public function remove_water(Request $request)
    {
        $param = $request->param();
        $param['info']['ids'] = $param['ids'];
        foreach (explode(',', $param['ids']) as $v) {
            $userDetail = UserModel::where('id', '=', $v)->find();;
            if (!$userDetail) {
                continue;
            }
            $condition = [];
            $condition[] = ['uac_uid', '=', $v];
            $condition[] = ['uac_done', '=', 0];
            $condition[] = ['uac_balancetype', '=', 0];#真金指定流水
            $_list = UserAwardCfgsModel::where($condition)->select();
            $appointWater = 0;
            foreach ($_list as $_v) {
                $detail = UserAwardCfgsModel::where('uac_id', '=', $_v['uac_id'])->find();
                if (!$detail) {
                    continue;
                }
                $appointWater += $detail->uac_water - $detail->uac_waterdone;
                $detail->uac_done = time();
                $detail->uac_waterdone = $detail->uac_water;
                $detail->save();
            }
            $msg = '清除用户流水:{:user_savings},指定流水:{:appoint_water}';
            $msgData = ['user_cjsavings' => $userDetail->cjsavings, 'appoint_water' => $appointWater];
            UserAdminLogModel::add_log($v, lang($msg, $msgData), Session::get("adminInfo.name"));
        }
        $wh[] = ['id', 'in', $param['ids']];
        UserModel::where($wh)->update(['savings' => 0]);
        return Respons::run(1, '', '', '', $param['info'], new UserModel(), lang('批量清零流水'));
    }

    //批量清零彩金流水
    public function remove_cjwater(Request $request)
    {
        $param = $request->param();
        $param['info']['ids'] = $param['ids'];
        foreach (explode(',', $param['ids']) as $v) {
            $userDetail = UserModel::where('id', '=', $v)->find();;
            if (!$userDetail) {
                continue;
            }
            $condition = [];
            $condition[] = ['uac_uid', '=', $v];
            $condition[] = ['uac_done', '=', 0];
            $condition[] = ['uac_balancetype', '=', 0];#真金指定流水
            $_list = UserAwardCfgsModel::where($condition)->select();
            $appointWater = 0;
            foreach ($_list as $_v) {
                $detail = UserAwardCfgsModel::where('uac_id', '=', $_v['uac_id'])->find();
                if (!$detail) {
                    continue;
                }
                $appointWater += $detail->uac_water - $detail->uac_waterdone;
                $detail->uac_done = time();
                $detail->uac_waterdone = $detail->uac_water;
                $detail->save();
            }
            $msg = '清除用户彩金流水{:user_cjsavings},指定流水:{:appoint_water}';
            $msgData = ['user_cjsavings' => $userDetail->cjsavings, 'appoint_water' => $appointWater];
            UserAdminLogModel::add_log($v, lang($msg, $msgData), Session::get("adminInfo.name"));
        }
        $wh[] = ['id', 'in', $param['ids']];
        UserModel::where($wh)->update(['cjsavings' => 0]);
        return Respons::run(1, '', '', '', $param['info'], new UserModel(), lang('批量清零彩金流水'));
    }

    public function reports(Request $request)
    {
        $rsps = (new UserFactory($request->token))->reports();
        return $rsps;
    }

    //列表
    public function online(Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        if (isset($param['username']) && $param['username'] != '') {
            $where[] = ["uo_uid", "=", $param['username']];
//            if (is_numeric($param['username'])) {
//                $where[]=["u.id|u.phone","=", $param['username']];
//            } else {
//                $where[]=["u.username|u.mail|u.phone","=", $param['username']];
//            }
        }
        if (isset($param['uo_dt']) && $param['uo_dt'] !== '') {
            $where[] = ["uo_dt", "=", $param['uo_dt']];
        }
        if (isset($param['offline_time']) && $param['offline_time'] != '') {
            $date = explode(' - ', $param['offline_time']);
            $where[] = ["uo_etime", ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ["uo_etime", "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['online_time']) && $param['online_time'] != '') {
            $date = explode(' - ', $param['online_time']);
            $where[] = ["uo_stime", ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ["uo_stime", "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['status']) && $param['status'] !== '') {
            $where[] = ["uo_onstate", "=", $param['status']];
        }
        $userOnlineModel = new UserOnlineModel();
        $rsps['count'] = $userOnlineModel->where($where)->count();
        $rsps['data'] = $userOnlineModel
            ->where($where)->limit((($page - 1) * $limit), $limit)
            ->order("uo_etime desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($rsps['data'] as &$v) {
            $user_temp = UserModel::where('id', '=', $v['uo_uid'])->field('username,mail,phone')->find();
            $v['username'] = $user_temp['username'];
            $v['mail'] = $user_temp['mail'];
            $v['phone'] = $user_temp['phone'];
            //处理上线时间和下线时间
            $v['uo_stime'] = date('Y-m-d H:i:s', $v['uo_stime']);
            $v['uo_etime'] = $v['uo_etime'] > 0 ? date('Y-m-d H:i:s', $v['uo_etime']) : '';
            $v['uo_intime'] = $v['uo_intime'] > 0 ? date('Y-m-d H:i:s', $v['uo_intime']) : '';
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //修改VIP
    public function vip_edit(Request $request)
    {
        $param = $request->param();
        $info = $param['info'];
        //获取原来会员数据
        $userDetail = UserModel::where('id', '=', $info['id'])->find();
        if (!$userDetail) {
            return Respons::run(0, [], lang('网络异常'));
        }
        //组合修改数据
        $save = [];
        $save['level_id'] = $info['vip_id'];
        $save['original_level_id'] = $userDetail->level_id;
        $res = $userDetail->save($save);
        if (!$res) {
            return Respons::run(0, [], lang('网络异常'));
        }
        return Respons::run(1, [], lang('修改成功'), '', $info, new UserModel(), lang('VIP修改'));
    }

    //还原VIP
    public function vip_reset(Request $request)
    {
        $info = $request->param();
        //获取原来会员数据
        $userDetail = UserModel::where('id', '=', $info['id'])->find();
        if (!$userDetail) {
            return Respons::run(0, [], lang('网络异常'));
        }
        //组合修改数据
        $save = [];
        $save['level_id'] = $userDetail->original_level_id;
        $save['original_level_id'] = 0;
        $res = $userDetail->save($save);
        if (!$res) {
            return Respons::run(0, [], lang('网络异常'));
        }
        //检测用户VIP升级
//        Queue::push("User@upgrade", ['id' => $info['id'], 'money' => 0], 'upgrade');
        return Respons::run(1, [], lang('还原成功'), '', $info, new UserModel(), lang('还原VIP到') . $userDetail->original_level_id);
    }


    public function reset_2fa(Request $request)
    {
        $info = $request->param();
        if (!isset($info['ids'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $upd = [];
        $upd['google_secret'] = '';
        $upd['goodsle_bind'] = 0;
        $upd['login_check'] = 0;
        $upd['login_password_check'] = 0;
        $upd['withdrawal_password_check'] = 0;
        $upd['bankcard_check'] = 0;
        $upd['withdrawal_check'] = 0;
        $UserGoogleModel = new UserGoogleModel;
        $w = [];
        $w[] = ['uid', 'in', $info['ids']];
        $res = $UserGoogleModel->where($w)->update($upd);
        return Respons::run($res ? 1 : 0, '', '', ['uid' => implode('|', $info['ids'])], '', $UserGoogleModel, lang('会员2FA重置'));
    }


    public function reset_with_pass(Request $request)
    {
        $info = $request->param();
        if (!isset($info['ids'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $upd = [];
        $upd['withdrawal_psd'] = '';
        $UserModel = new UserModel;
        $w = [];
        $w[] = ['id', 'in', $info['ids']];
        $res = $UserModel->where($w)->update($upd);
        return Respons::run($res ? 1 : 0, '', '', ['id' => implode('|', $info['ids'])], '', $UserModel, lang('会员提现密码重置'));
    }


    public function user_address(Request $request)
    {
        $param = $request->param();
        $limit = $param['limit'];
        $where = [];
        if (isset($param['key']) && $param['key'] != '') {
            $where[] = ["a.username|b.address|c.pay_sys_sign|c.pay_name|b.uid", "=", $param['key']];
        }
        $rsps['data'] = UserModel::alias('a')
            ->join('user_address b', 'a.id = b.uid')
            ->join('pay_set c', 'c.id = b.pay_id')
            ->field('a.username, b.address, c.usdt_type, c.pay_sys_sign, c.pay_name, b.time, b.uid, b.id')
            ->where($where)
            ->filter(function ($d) {
                $d['time'] = date('Y-m-d H:i:s', $d['time']);
                return $d;
            })
            ->paginate($limit, false, ['query' => $param]);
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function user_address_del(Request $request)
    {
        $param = $request->param();
        $UserAddressModel = new UserAddressModel();
        $before = [];
        $before['id'] = $param['id'];
        $UserAddress = $UserAddressModel->where("id", "in", $param['id'])->column('address');
        $before['address'] = implode(',', $UserAddress);
        $res = $UserAddressModel->where("id", "in", $param['id'])->delete();
        return Respons::run($res ? 1 : 0, '', '', $before, '', $UserAddressModel, lang('三方收款地址删除'));
    }


    public function plays_mail(Request $request)
    {
        $param = $request->param();
        $ids = $param['ids'];
        $w = [];
        $w[] = ['id', 'in', $ids];
        $w[] = ['admin_mail_notify_sta', '=', 1];
        $email = UserModel::where($w)->column('mail');
        $email = array_filter($email);
        $t = $param['info']['email_title'] ?? '';
        if (empty($t)) {
            return Respons::run(0, [], lang('邮件模板不存在'));
        }
        if (count($email) == 0) {
            return Respons::run(0, [], lang('没有可以发送的邮件地址'));
        }
        $MailNotifyTemplateModel = new MailNotifyTemplateModel();
        $w = [];
        $w[] = ['id', '=', $t];
        $w[] = ['state', '=', 1];
        $oneData = $MailNotifyTemplateModel->where($w)->find();
//        $type = explode('_', $t)[0];
        //type 填写类型  设置中的字段是 类型加title 比如 verify_title
//        $other['notify_type'] = $t;
        $other = [];
        $other['title'] = $oneData['title'];
        $other['content'] = $oneData['content'];
//        EmailCode::notifyMail($email, $other);
        foreach ($email as $k => $v) {
            $other['email'] = $v;
            Queue::push('SendMsg@play_email', $other, 'import');
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}