<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\Utils;
use app\common\model\{GameBackConfigModel, GameSetModel, GameTypeModel, GameWaterRatesModel, UserLevelModel};
use app\manage\factory\GameSetFacctory;
use think\facade\{Config, Db};
use think\Request;

class GameSet
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    public function __construct(GameSetModel $GameSetModel, GameTypeModel $GameTypeModel, GameBackConfigModel $GameBackConfigModel)
    {
        $this->GameSetModel = $GameSetModel;
        $this->GameTypeModel = $GameTypeModel;
        $this->GameBackConfigModel = $GameBackConfigModel;
    }

    //列表
    public function list(Request $request)
    {
        $param = $request->param();
        $arr = [0];
        $type = array_diff(config::get('custom.three_game'), $arr);
        $group = config::get('custom.three_game_group');
        $type_data = [];
        foreach ($type as $k => $v) {
            $type_data[$k] = $group[$k];
        }
        $game_type = $this->GameTypeModel->order("sort desc,id desc")->where(['state' => 1])->select();
        $thirds = Db::name('third_configs')->column("tc_name", "tc_id");
        if (isset($param['select'])) {
            $page = $param['page'];
            $limit = $param['limit'];
            $where = [];
            $rsps['data'] = $this->GameSetModel
                ->with('type')
                ->when((isset($param['name']) && !empty($param['name'])), function ($query) use ($param) {
                    $query->where('game_name', 'like', "%{$param['name']}%");
                })
                ->when((isset($param['game_sys_sign']) && !empty($param['game_sys_sign'])), function ($query) use ($param) {
                    $query->where('game_sys_sign', $param['game_sys_sign']);
                })
                ->when((isset($param['type']) && !empty($param['type'])), function ($query) use ($param) {
                    $query->where('type', $param['type']);

                })
                ->when((isset($param['tc_id']) && !empty($param['tc_id'])), function ($query) use ($param) {
                    $query->where('tc_id', $param['tc_id']);

                })
                ->where($where)
                ->order("id desc")
                ->paginate($limit, false, ['query' => $param]);
            foreach ($rsps['data'] as &$value) {
                $value['tc_name'] = isset($thirds[$value['tc_id']]) ? $thirds[$value['tc_id']] : '';
            }
            $rsps['thirds'] = $thirds;
            $rsps['type'] = $type;
            $rsps['game_type'] = $game_type;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }
        if (isset($param['type'])) {
            return Respons::run(1, $type_data);
        }
        if (isset($param['game_type'])) {
            return Respons::run(1, $game_type);
        }
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\GameSetValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            $wh['game_sys_sign'] = $info['game_sys_sign'];
            $wh['game_code'] = $info['game_code'];
            $onId = $this->GameSetModel->where($wh)->value('id');
            if ($onId > 0) {
                return Respons::run(0, [], lang('每款游戏都是唯一的'));
            }
            Db::startTrans();
            try {
                $info['game_param'] = isset($info['game_param']) ? json_encode($info['game_param']) : [];
                $res = $this->GameSetModel->save($info);
                Db::commit();
                return Respons::run($res ? 1 : 0, '', '', '', $info, $this->GameSetModel, lang('三方游戏添加'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        if (isset($param['game_param'])) {
            $arr = [0];
            $type = array_diff(config::get('custom.three_game'), $arr);
            $param = config::get('game_param.three_game');
            $param = [];
            foreach ($type as $k => $v) {
                $i = 0;
                $par_name = strtolower($k) . '_par';
                $param_arr = config::get("game_param.{$par_name}");
                foreach ($param_arr as $key => $val) {
                    $param[$k][$i]['key_name'] = $key;
                    $param[$k][$i]['key_value'] = '';
                    $param[$k][$i]['pal'] = lang($val);
                    $i++;
                }
            }
            $thirds = Db::name('third_configs')->select();
            $param['thirds'] = $thirds;
            return Respons::run(1, $param);
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $upd['id'] = $info['id'];
            $upd['game_name'] = $info['game_name'];
            $upd['game_code'] = $info['game_code'];
            $upd['game_status'] = $info['game_status'];
            $upd['wallet_type'] = $info['wallet_type'];
            $upd['min_transfer'] = $info['min_transfer'];
            $upd['max_transfer'] = $info['max_transfer'];
            $upd['sort'] = $info['sort'];
            $upd['icon'] = $info['icon'];
            $upd['type'] = $info['type'];
            $upd['tc_id'] = $info['tc_id'];
            $upd['is_hot'] = $info['is_hot'];
            $upd['content'] = $info['content'];
            $upd['game_param'] = isset($info['game_param']) ? json_encode($info['game_param']) : [];

            try {
                validate(\app\common\validate\GameSetValidate::class)->scene('edit')->check($upd);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }

            Db::startTrans();
            try {
                [$model, $data] = getOneData($this->GameSetModel, ['id' => $info['id']]);
                $res = $model->save($upd);
                Db::commit();
                return Respons::run($res ? 1 : 0, '', '', $data, $upd, $this->GameSetModel, lang('修改第三方游戏资料'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        if (isset($param['find'])) {
            $GameSetModel = new GameSetModel();
            $thirds = Db::name('third_configs')->select();
            $res = $GameSetModel->where("id", $param['id'])->find();
            $res['game_param'] = json_decode($res['game_param']) ?? [];
            $res['thirds'] = $thirds;
            return Respons::run(1, $res);
        }
    }

    /**
     * 游戏分类
     */
    public function type(\think\Request $request)
    {
        $rsps = (new GameSetFacctory($request->token->uid, $this->GameTypeModel))->type();
        return $rsps;
    }


    //添加
    public function type_add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\GameTypeValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            unset($info['id']);
            Db::startTrans();
            try {
                $res = $this->GameTypeModel->save($info);
                Db::commit();
                UserLevelModel::getVipGameWaterAndBackRate(true);
                return Respons::run($res ? 1 : 0, '', '', '', $info, $this->GameTypeModel, lang('添加游戏分类'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
            return Respons::run(0);
        }
    }

    //编辑
    public function type_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['find'])) {
            $data = $this->GameTypeModel
                ->withoutField('create_time, update_time')
                ->where(['id' => $param['id']])
                ->find();
            $data['icon'] = Util::img_url($data['icon']);
            $code = 1;
            return Respons::run(compact('code', 'data'));
        }
        if (isset($param['edit'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\GameTypeValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            Db::startTrans();
            try {
                [$model, $data] = getOneData($this->GameTypeModel, ['id' => $info['id']]);
                $res = $model->allowField(['name', 'sort', 'update_time', 'icon', 'default_icon', 'state', 'min_amount', 'max_amount', 'type'])->save($info);
                Db::commit();
                UserLevelModel::getVipGameWaterAndBackRate(true);
                return Respons::run($res ? 1 : 0, '', '', $data, $info, $this->GameTypeModel, lang('修改游戏分类'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    /*删除*/
    public function type_delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = $this->GameTypeModel->where([["id", 'in', $id]])->delete();
            UserLevelModel::getVipGameWaterAndBackRate(true);
            return Respons::run($res ? 1 : 0, '', '', $param, '', $this->GameTypeModel, lang('删除游戏分类'));
        }
    }

    //返水配置列表
    public function backconfig_list(Request $request)
    {
        $param = $request->param();
        $id = $param['id'] ?? 0;
        if (isset($param['select'])) {
            $limit = $param['limit'];
            $rsps['data'] = $this->GameBackConfigModel->where(['gbc_gtid' => $id])
                ->filter(function ($data) {
                    $data['gbc_viplvl'] = UserLevelModel::where(['id' => $data['gbc_viplvl']])->value('level_name');
                })->paginate($limit, false, ['query' => $param]);
            $rsps['code'] = 1;
            $rsps['level'] = UserLevelModel::select();
            return Respons::run($rsps);
        }
    }

    //返水配置添加
    public function backconfig_add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\GameBackConfigValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if ($this->GameBackConfigModel->where(['gbc_gtid' => $info['gbc_gtid'], 'gbc_viplvl' => $info['gbc_viplvl']])->value('gbc_id')) {
                return Respons::run(0, [], lang('等级配置已存在'));
            }
            Db::startTrans();
            try {
                $res = $this->GameBackConfigModel->insertGetId($info);
                $info['gbc_id'] = $res;
                Db::commit();
                UserLevelModel::getVipGameWaterAndBackRate(true);
                return Respons::run($res ? 1 : 0, '', '', '', $info, $this->GameBackConfigModel, lang('添加游戏分类返水配置'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
            return Respons::run(0);
        }
    }

    //返水配置删除
    public function backconfig_delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = $this->GameBackConfigModel->where([["gbc_id", 'in', $id]])->delete();
            UserLevelModel::getVipGameWaterAndBackRate(true);
            $param['gbc_id'] = $id;
            return Respons::run($res ? 1 : 0, '', '', $param, '', $this->GameBackConfigModel, lang('删除游戏分类返水配置'));
        }
    }

    //返水配置编辑
    public function backconfig_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['find'])) {
            $data = $this->GameBackConfigModel->where(['gbc_id' => $param['id']])->find();
            $code = 1;
            $data['level'] = UserLevelModel::select();
            return Respons::run(compact('code', 'data'));
        }
        if (isset($param['edit'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\GameBackConfigValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            if ($this->GameBackConfigModel->where([['gbc_gtid', '=', $info['gbc_gtid']], ['gbc_viplvl', '=', $info['gbc_viplvl']], ['gbc_id', '<>', $info['gbc_id']]])->value('gbc_id')) {
                return Respons::run(0, [], lang('等级配置已存在'));
            }
            Db::startTrans();
            try {
                $model = $this->GameBackConfigModel->where(['gbc_id' => $info['gbc_id']])->find();
                $data = $model->getData();
                $res = $model->allowField(['gbc_gtid', 'gbc_viplvl', 'gbc_rate'])->save($info);
                UserLevelModel::getVipGameWaterAndBackRate(true);
                Db::commit();
                return Respons::run($res ? 1 : 0, '', '', $data, $info, $this->GameBackConfigModel, lang('修改游戏分类返水配置'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    //设置游戏类型流水扣减比率 扣减流水时将会乘以此比率
    public function set_game_water_rate(Request $request)
    {
        $params = $request->param()['info'];
        $gtid = $params['gtid'] ?? 0;
        //组合设置数组，方便下面循环设置
        $setArr = [];
        //处理真金
        if ($params['zj_rate']) {
            if ($params['zj_rate'] >= 1 || $params['zj_rate'] <= 0) {
                return Respons::run(0, [], '真金扣减比例设置错误');
            }
            if ($params['zj_set_object'] == 0) {
                //所有
                $setArr[] = ['gid' => 0, 'wtype' => 1, 'rate' => $params['zj_rate'], 'oldrate' => -1];
            } elseif ($params['zj_set_object'] == -1) {
                //指定游戏
                if (!$params['zj_gid']) {
                    return Respons::run(0, [], '请选择修改的游戏');
                }
                foreach (explode(',', $params['zj_gid']) as $gid) {
                    $setArr[] = ['gid' => $gid, 'wtype' => 1, 'rate' => $params['zj_rate'], 'oldrate' => -1];
                }
            } else {
                //指定旧比例设置
                $setArr[] = ['gid' => 0, 'wtype' => 1, 'rate' => $params['zj_rate'], 'oldrate' => $params['zj_set_object']];
            }
        }
        //处理彩金
        if ($params['cj_rate']) {
            if ($params['cj_rate'] >= 1 || $params['cj_rate'] <= 0) {
                return Respons::run(0, [], '真金扣减比例设置错误');
            }
            if ($params['cj_set_object'] == 0) {
                //所有
                $setArr[] = ['gid' => 0, 'wtype' => 1, 'rate' => $params['cj_rate'], 'oldrate' => -1];
            } elseif ($params['cj_set_object'] == -1) {
                //指定游戏
                if (!$params['cj_gid']) {
                    return Respons::run(0, [], '请选择修改的游戏');
                }
                foreach (explode(',', $params['cj_gid']) as $gid) {
                    $setArr[] = ['gid' => $gid, 'wtype' => 1, 'rate' => $params['cj_rate'], 'oldrate' => -1];
                }
            } else {
                //指定旧比例设置
                $setArr[] = ['gid' => 0, 'wtype' => 1, 'rate' => $params['cj_rate'], 'oldrate' => $params['cj_set_object']];
            }
        }
        $successNum = $errorNum = 0;
        foreach ($setArr as $v) {
            if (GameWaterRatesModel::setGameWaterRates($gtid, $v['gid'], $v['wtype'], $v['rate'], $v['oldrate'])) {
                $successNum++;
            } else {
                $errorNum++;
            }
        }
        if ($errorNum) {
            return Respons::run(0, [], '设置成功' . $successNum . '个，失败' . $errorNum . '个');
        }
        return Respons::run(1, '', '设置成功', '', ['set_rate' => json_encode($params)], new GameTypeModel(), lang('设置分类流水扣减比例'));
    }

    //获取一个游戏分类中所有的流水扣减比率
    public function get_game_water_rates(Request $request)
    {
        $gtid = $request->param('gtid/d');
        //初始化游戏流水比例
        GameWaterRatesModel::initRates();
        return Respons::run(1, GameWaterRatesModel::get_game_water_rates($gtid));
    }

    //游戏流水扣减比率列表
    public function game_water_rates_list(Request $request)
    {
        $gtid = $request->param('gtid/d');
        $gname = $request->param('g_name/s');
        $wtype = $request->param('wtype/d');
        $rate = $request->param('rate/f');
        $limit = $request->param('limit/d');
        $limit = $limit ?? 10;
        $page = $request->param('page/d');
        $page = $page ?? 1;
        $offset = ($page - 1) * $limit;
        $page = ['offset' => $offset, 'limit' => $limit];
        $datas = GameWaterRatesModel::game_water_rates_list($page, $gtid, $gname, $wtype, $rate);
        $datas['code'] = 1;
        $datas['type_list'] = Utils::getOptions('game_type', ['id', 'name'], ['state' => 1]);
        return Respons::run($datas);
    }

    //编辑
    public function game_water_rates_edit(Request $request)
    {
        $param = $request->param();
        $gameWaterRatesModel = new GameWaterRatesModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $condition = [];
            $condition[] = ['gwr_gtid', '=', $info['gwr_gtid']];
            $condition[] = ['gwr_gtid', '=', $info['gwr_gtid']];
            $data = $gameWaterRatesModel->where($condition)->find();
            $res = $gameWaterRatesModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $gameWaterRatesModel, lang('游戏流水扣减比率修改'));
        }
        if (isset($param['find'])) {
            $condition = [];
            $condition[] = ['gwr_gtid', '=', $param['gwr_gtid']];
            $condition[] = ['gwr_gtid', '=', $param['gwr_gtid']];
            $res = $gameWaterRatesModel->where($condition)->find();
            return Respons::run(1, $res);
        }
    }
}