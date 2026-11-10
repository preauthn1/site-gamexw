<?php

namespace app\manage\controller;

use app\common\FastGame\Base\Redis;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\lib\Util;
use app\common\model\AdminLogModel;
use app\common\model\FakeGameSet;
use app\common\model\GameTypeModel;
use app\common\model\GameBetsModel;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\model\GameWaterRatesModel;
use app\common\model\UserModel;
use app\common\model\UserAdminLogModel;
use app\common\redis\CommomRedis;
use app\common\service\BetService;
use app\common\service\GameService;
use app\common\Utils;
use app\manage\factory\GameFactory;
use app\model\GameApis;
use app\model\Games;
use app\model\ThirdConfigs;
use think\facade\Cache;
use think\facade\Session;
use think\facade\View;
use think\Request;
use think\facade\Config;
use think\facade\Db;

class Game extends Base
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    public function lists(\think\Request $request)
    {
        $rsps = (new GameFactory())->lists();
        return $rsps;
    }

    //编辑
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $rlt = self::addGame($info);
            if ($rlt == 2) {
                return Respons::run(0, [], lang('游戏代码已存在，请重新填写'));
            }
            return Respons::run(1, [], lang('添加成功'));
            /*$game_info = Db::name('games')->where(['g_code' => $info['g_code'], 'g_tcid' => $info['g_tcid']])->find();
            if (!empty($game_info)) {
                return Respons::run(0,[],lang('游戏代码已存在，请重新填写'));
            }
            $upd = [];
            $upd['g_tcid']=$info['g_tcid'];
            $upd['g_web_tcid']=$info['g_web_tcid'];
            $upd['g_code']=$info['g_code'];
            $upd['g_tag']=$info['g_tag'];
            $upd['g_icon']=$info['g_icon'];
            $upd['g_state']=$info['g_state'];
            $upd['g_lock']=$info['g_lock'];
            $upd['g_type']=$info['g_type'];
            $upd['g_sort']=$info['g_sort'];
            $upd['g_hot']=$info['g_hot'];
            $upd['g_show']=$info['g_show'];
            $upd['g_sports']=$info['g_sports'];
            $upd['g_backstatus']=$info['g_backstatus'];
            $upd['g_fystatus']=$info['g_fystatus'];
            $upd['g_keyword']=trim($info['g_keyword']);
            $upd['g_is_new']=$info['g_is_new'];
            $upd['g_is_optimum']=$info['g_is_optimum'];
            $upd['g_is_self']=$info['g_is_self'];
            $upd['g_exfield']=trim($info['g_exfield']);
            $upd['g_remarks']=trim($info['g_remarks']);
            $res = Db::name('games')->insertGetId($upd);
            if($res){
                $upd2 = [];
                $upd2['gi_gid']=$res;
                $upd2['gi_lang']= "CN";//$info['gi_lang'];
                $upd2['gi_thirdlang']=$info['gi_thirdlang'];
                $upd2['gi_name']=$info['gi_name'];
                $upd2['gi_info']=$info['gi_info'];
                $upd2['gi_headimg']=$info['gi_headimg'];
                $upd2['gi_content']=$info['gi_content'];
                $res2 = Db::name('game_infos')->insertGetId($upd2);
                Db::name('game_configs')->insertGetId(["gc_gid" => $res]);
                //设置游戏流水扣减比率
                GameWaterRatesModel::setGameWaterRates($info['g_type'],$res,1,-1,0);
                GameWaterRatesModel::setGameWaterRates($info['g_type'],$res,2,-1,0);
            }
            \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
            return Respons::run($res?1:0);*/
        }
    }

    public static function addGame($info)
    {
        $game_info = Db::name('games')->where(['g_code' => $info['g_code'], 'g_tcid' => $info['g_tcid']])->find();
        if (!empty($game_info)) {
            return 2;
        }
        $upd = [];
        $upd['g_tcid'] = $info['g_tcid'];
        $upd['g_web_tcid'] = $info['g_web_tcid'];
        $upd['g_code'] = $info['g_code'];
        $upd['g_tag'] = $info['g_tag'];
        isset($info['g_icon']) && $upd['g_icon'] = $info['g_icon'];
        isset($info['g_state']) && $upd['g_state'] = $info['g_state'];
        isset($info['g_lock']) && $upd['g_lock'] = $info['g_lock'];
        isset($info['g_type']) && $upd['g_type'] = $info['g_type'];
        isset($info['g_sort']) && $upd['g_sort'] = $info['g_sort'];
        isset($info['g_hot']) && $upd['g_hot'] = $info['g_hot'];
        isset($info['g_show']) && $upd['g_show'] = $info['g_show'];
        isset($info['g_sports']) && $upd['g_sports'] = $info['g_sports'];
        isset($info['g_slot']) && $upd['g_slot'] = $info['g_slot'];
        isset($info['g_backstatus']) && $upd['g_backstatus'] = $info['g_backstatus'];
        isset($info['g_fystatus']) && $upd['g_fystatus'] = $info['g_fystatus'];
        isset($info['g_keyword']) && $upd['g_keyword'] = trim($info['g_keyword']);
        isset($info['g_is_new']) && $upd['g_is_new'] = $info['g_is_new'];
        isset($info['g_is_optimum']) && $upd['g_is_optimum'] = $info['g_is_optimum'];
        isset($info['g_is_self']) && $upd['g_is_self'] = $info['g_is_self'];
        isset($info['g_exfield']) && $upd['g_exfield'] = trim($info['g_exfield']);
        isset($info['g_remarks']) && $upd['g_remarks'] = trim($info['g_remarks']);
        $res = Db::name('games')->insertGetId($upd);
        if ($res) {
            $upd2 = [];
            $upd2['gi_gid'] = $res;
            $upd2['gi_lang'] = "CN";//$info['gi_lang'];
            $upd2['gi_thirdlang'] = $info['gi_thirdlang'];
            $upd2['gi_name'] = $info['gi_name'];
            isset($info['gi_info']) && $upd2['gi_info'] = $info['gi_info'];
            isset($info['gi_headimg']) && $upd2['gi_headimg'] = $info['gi_headimg'];
            isset($info['gi_content']) && $upd2['gi_content'] = $info['gi_content'];
            $res2 = Db::name('game_infos')->insertGetId($upd2);
            Db::name('game_configs')->insertGetId(["gc_gid" => $res]);
            //设置游戏流水扣减比率
            GameWaterRatesModel::setGameWaterRates($info['g_type'], $res, 1, -1, 0);
            GameWaterRatesModel::setGameWaterRates($info['g_type'], $res, 2, -1, 0);
        }
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        return $res ? 1 : 0;
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            isset($info['g_icon']) && $upd['g_icon'] = $info['g_icon'];
            isset($info['g_state']) && $upd['g_state'] = $info['g_state'];
            isset($info['g_lock']) && $upd['g_lock'] = $info['g_lock'];
            isset($info['g_type']) && $upd['g_type'] = $info['g_type'];
            isset($info['g_sort']) && $upd['g_sort'] = $info['g_sort'];
            isset($info['g_hot']) && $upd['g_hot'] = $info['g_hot'];
            isset($info['g_show']) && $upd['g_show'] = $info['g_show'];
            isset($info['g_sports']) && $upd['g_sports'] = $info['g_sports'];
            isset($info['g_slot']) && $upd['g_slot'] = $info['g_slot'];
            isset($info['g_backstatus']) && $upd['g_backstatus'] = $info['g_backstatus'];
            isset($info['g_fystatus']) && $upd['g_fystatus'] = $info['g_fystatus'];
            isset($info['g_keyword']) && $upd['g_keyword'] = trim($info['g_keyword']);
            isset($info['g_is_new']) && $upd['g_is_new'] = $info['g_is_new'];
            isset($info['g_is_optimum']) && $upd['g_is_optimum'] = $info['g_is_optimum'];
            isset($info['g_is_self']) && $upd['g_is_self'] = $info['g_is_self'];
            isset($info['g_exfield']) && $upd['g_exfield'] = trim($info['g_exfield']);
            isset($info['g_remarks']) && $upd['g_remarks'] = trim($info['g_remarks']);
            $admin = Session::get("adminInfo");
            $upd = [];
            $upd['g_id'] = $info['g_id'];

            $upd['g_code'] = $info['g_code'];
            $upd['g_tag'] = $info['g_tag'];
            $upd['g_icon'] = $info['g_icon'];
            $upd['g_state'] = $info['g_state'];
            $upd['g_lock'] = $info['g_lock'];
            $upd['g_type'] = $info['g_type'];
            $upd['g_tcid'] = $info['g_tcid'];
            $upd['g_web_tcid'] = $info['g_web_tcid'];
            $upd['g_rtp'] = $info['g_rtp'];

            $upd['g_sort'] = $info['g_sort'];
            $upd['g_hot'] = $info['g_hot'];
            $upd['g_show'] = $info['g_show'];
            $upd['g_sports'] = $info['g_sports'];
            $upd['g_slot'] = isset($info['g_slot']) ? $info['g_slot'] : 0;
            $upd['g_backstatus'] = $info['g_backstatus'];
            $upd['g_fystatus'] = $info['g_fystatus'];
            $upd['g_keyword'] = trim($info['g_keyword']);
            $upd['g_is_new'] = $info['g_is_new'];
            $upd['g_is_optimum'] = $info['g_is_optimum'];
            $upd['g_is_self'] = $info['g_is_self'];
            $upd['g_exfield'] = trim($info['g_exfield']);
            $upd['g_remarks'] = isset($info['g_remarks']) ? trim($info['g_remarks']) : '';
            $upd['g_cj'] = $info['g_cj'];
            $res = Db::name('games')->update($upd);

            $upd2 = [];
            $upd2['gi_thirdlang'] = $info['gi_thirdlang'];
            $upd2['gi_name'] = $info['gi_name'];
            $upd2['gi_info'] = $info['gi_info'];
            $upd2['gi_headimg'] = $info['gi_headimg'];
            $upd2['gi_content'] = $info['gi_content'];
            $res2 = Db::name('game_infos')->where(['gi_gid' => $info['g_id']])->update($upd2);

            //修改游戏状态是同步修改榜单管理中游戏配置中的状态
            $condition = [];
            $condition[] = ['gid', '=', $upd['g_id']];#游戏id
            if (isset($upd['g_tcid'])) {
                $condition[] = ['tcid', '=', $upd['g_tcid']];#第三方平台id
            }
            $fakeGameSetUpdate = [];
            if (in_array($upd['g_state'], [0, 2])) {
                //关闭或维护中
                $fakeGameSetUpdate['status'] = 0;
            } else {
                //开启
                $fakeGameSetUpdate['status'] = 1;
            }
            FakeGameSet::update($fakeGameSetUpdate, $condition);
            \app\common\FastGame\Base\Game::buildGamesInfoCache(true);

            return Respons::run($res === false ? 0 : 1);
        }
        if (isset($param['find'])) {
            $res = Db::name('games')->alias('a')->join('game_infos i', 'a.g_id = i.gi_gid')->join('third_configs t', 'a.g_tcid = t.tc_id')
                ->where("a.g_id", $param['id'])->field('a.*,i.*,t.tc_id,t.tc_name')
                ->find();

            $game_rtp = '';
            $game = Db::name('games')->where("g_id", $param['id'])->find();
            if ($game['g_tcid'] == 6) {
                $rtp_file = base_path("common") . "FastGame/Rtp_tgg.php";
                if (file_exists($rtp_file)) {
                    $rtp = require($rtp_file);
                    $game_rtp = $rtp[$game['g_code']] ?? '';
                }
            }
            $game['g_icon'] = Util::img_url($game['g_icon']);
            $thirds = Db::name('third_configs')->select();
            $rsps['thirds'] = $thirds;
            $rsps['game_rtp'] = $game_rtp;
            return Respons::run(1, $res);
        }
        if (isset($param['add'])) {
            return $this->add($request);
        }
    }

    // 删除游戏
    public function delete()
    {
        $id = $this->request->param('id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }

        AdminLogModel::addLog($id, '', 'dq_games', '删除游戏');
        $res = Db::name('games')->where("g_id", "IN", $id)->update(['g_is_del' => 1]);
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        return $res ? $this->success() : $this->error();
    }

    // 批量修改游戏状态
    public function batch_status()
    {
        $g_tcid = $this->request->param('g_tcid/d');
        $g_state = $this->request->param('g_state/d');
        $g_show = $this->request->param('g_show/d');
        $g_cj = $this->request->param('g_cj/d');

        if (!in_array($g_state, [0, 1, 2])) {
            return $this->error('状态有误');
        }
        $third = Db::name('third_configs')
            ->where('tc_id', $g_tcid)
            ->find();

        if (empty($third)) {
            return $this->error('平台信息不存在');
        }
        $map = [
            //未锁定是游戏才能批量修改状态
            'g_lock' => 0
        ];
        $update = [

        ];
        Db::name('games')->where('g_tcid', $g_tcid)->where($map)->update([
            'g_state' => $g_state,
            'g_show' => $g_show,
            'g_cj' => $g_cj,
        ]);
        $g_state_text = ['关闭', '开启', '维护'];
        Utils::addOptLog("状态:[{$g_state_text[$g_state]}]" . ";平台:{$third['tc_name']}", '', 'qd_games', '批量修改游戏状态');

        FakeGameSet::where('tcid', $g_tcid)->update([
            'status' => $g_state == 1 ? 1 : 0
        ]);
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        return $this->success();
    }

    // 勾选批量修改游戏状态
    public function batch_select_status()
    {
        $g_id = $this->request->param('g_id');
        $info = $this->request->param('info');
        $g_state = $info['g_state'];
        $g_show = $info['g_show'];
        $g_lock = $info['g_lock'];
        $g_cj = $info['g_cj'];
        $update = [];
        if ($g_state != "") {
            $update['g_state'] = $g_state;
        }
        if ($g_show != "") {
            $update['g_show'] = $g_show;
        }
        if ($g_lock != "") {
            $update['g_lock'] = $g_lock;
        }
        if ($g_cj != "") {
            $update['g_cj'] = $g_cj;
        }
        if (isset($info['g_type']) && $info['g_type'] !== "") {
            $update['g_type'] = $info['g_type'];
        }
        Db::name('games')->whereIn('g_id', $g_id)->update($update);
        $g_state_text = ['关闭', '开启', '维护'];
        $g_id = json_encode($g_id);
        if ($g_state != "") {
            Utils::addOptLog("状态:[{$g_state_text[$g_state]}]" . ";游戏ID:{$g_id}", '', 'qd_games', '勾选批量修改游戏状态');
        }
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        return $this->success();
    }

    //列表
    public function third(\think\Request $request)
    {
        $rsps = (new GameFactory())->third();
        return $rsps;
    }

    //前端三方平台列表
    public function third_web(Request $request)
    {
        $rsps = (new GameFactory())->third_web();
        return $rsps;
    }

    //添加
    public function third_add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if (!isset($info['tc_code']) || empty($info['tc_code'])) {
                return Respons::run(0, [], '平台代号不能为空');
            }
            $info['tc_intime'] = date("Y-m-d H:i:s");
            if (!isset($info['tc_langmap']) || empty($info['tc_langmap'])) {
                $info['tc_langmap'] = "CN";
            }
            $tc_code = Db::name('third_configs')->where('tc_code', $info['tc_code'])->find();
            if (!empty($tc_code)) {
                return Respons::run(0, [], '平台代号重复');
            }
            Utils::addOptLog(json_encode($info), '', 'third_configs', '三方平台配置');
            $res = Db::name('third_configs')->insertGetId($info);
            //设置平台中奖手续费缓存
            GameService::getOrSetPlatformFee($res, $info['tc_winning_fee'] ?? 0);
            return Respons::run($res ? 1 : 0);
        }
    }

    //前端平台添加
    public function third_add_web(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['tc_id']);
            $res = Db::name('third_configs_web')->insertGetId($info);
            return Respons::run($res ? 1 : 0);
        }
    }

    //编辑
    public function third_edit(Request $request)
    {
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        $sys = $CommomRedis->systemInfo();
        if (isset($param['edit'])) {
            $info = $param['info'];
            //状态为关闭时，判断是否有未删除的游戏
            if ($info['tc_status'] == 0) {
                $gId = GamesModel::where([['g_tcid', '=', $info['tc_id']], ['g_is_del', '=', 0]])->column("g_id");
                if (!empty($gId)) {
                    $redisKey = 'get_third_configs_status_' . $info['tc_id'];
                    //先从缓存获取
                    if (!$CommomRedis->has($redisKey)) {
//                      //设置缓存三分钟
                        $CommomRedis->set($redisKey, $info['tc_id'], 180);
                        return $this->error('存在游戏未关闭，再次确认忽略');
                    }
                }
            }
            $admin = Session::get("adminInfo.username");
            $config = Db::name('third_configs')->where("tc_id", $info['tc_id'])->find();
            if (empty($config)) {
                return $this->error('数据不存在');
            }
            $upd['tc_id'] = $info['tc_id'];
            $upd['tc_name'] = $info['tc_name'];
            if ($admin == 'manage') {
                $upd['tc_code'] = $info['tc_code'];
                $upd['tc_set_code'] = $info['tc_set_code'];
                $upd['tc_api_urlpre'] = $info['tc_api_urlpre'];
                $upd['tc_api_prikey'] = $info['tc_api_prikey'];
                $upd['tc_api_signkey'] = $info['tc_api_signkey'];
                $upd['tc_tag'] = $info['tc_tag'];
                $upd['tc_token'] = $info['tc_token'];
                $upd['tc_tokentime'] = $info['tc_tokentime'];
                $upd['tc_currency'] = $info['tc_currency'];
                $upd['tc_proxyconfig'] = $info['tc_proxyconfig'];
            }
            $upd['tc_exchangerate'] = $info['tc_exchangerate'];
            $upd['tc_min_bet'] = $info['tc_min_bet'];
            $upd['tc_exchangeint'] = $info['tc_exchangeint'];
            if (isset($info['tc_disable_agent_ids'])) {
                if (strpos($info['tc_disable_agent_ids'], ',')) {
                    $upd['tc_disable_agent_ids'] = implode(',', $info['tc_disable_agent_ids']);
                } else {
                    $upd['tc_disable_agent_ids'] = $info['tc_disable_agent_ids'];
                }
            }
            $upd['tc_max_bet'] = $info['tc_max_bet'];
            $upd['tc_status'] = $info['tc_status'];
            $upd['is_new_tab'] = $info['is_new_tab'];
            $upd['sort'] = $info['sort'];
            $upd['icon'] = $info['icon'];
            $upd['simple_icon'] = $info['simple_icon'];
            $upd['tc_langmap'] = $info['tc_langmap'];
            $upd['tc_timezone'] = $info['tc_timezone'] ?? '';
            $upd['tc_winning_fee'] = $info['tc_winning_fee'] ?? 0;
            $upd['country'] = $info['country'] ? json_encode($info['country']) : null;
            Utils::addOptLog(json_encode($config), json_encode($upd), 'third_configs', '修改三方平台配置');
            $res = Db::name('third_configs_web')->update($upd);

            //设置平台中奖手续费缓存
            GameService::getOrSetPlatformFee($info['tc_id'], $info['tc_winning_fee'] ?? 0);

            ThirdConfigs::clearPlatformConfigCache($info['tc_code']);
            //获取平台信息
            $cfg = Games::getConfigs(null, $info['tc_id']);
            Redis::hset('trdcfgs', $info['tc_code'], json_encode($cfg));
            return Respons::run($res !== false ? 1 : 0);
        }
        if (isset($param['find'])) {
            $res = Db::name('third_configs')->where("tc_id", $param['id'])->find();
            $res['country'] = $res['country'] ? json_decode($res['country'], true) : [];
            if (empty($res['icon']) || $res['icon'] == "/static/admin/img/add.png") {
                $res['icon'] = $sys['game_default_pic'];
            }
            return Respons::run(1, $res);
        }
    }

    //编辑
    public function third_edit_web(Request $request)
    {
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        $sys = $CommomRedis->systemInfo();
        if (isset($param['edit'])) {
            $info = $param['info'];
            //状态为关闭时，判断是否有未删除的游戏
            $upd['tc_id'] = $info['tc_id'];
            $upd['tc_name'] = $info['tc_name'];
            $upd['tc_status'] = $info['tc_status'];
            $upd['sort'] = $info['sort'];
            $upd['icon'] = $info['icon'];
            $upd['simple_icon'] = $info['simple_icon'];
            $res = Db::name('third_configs')->update($upd);
            return Respons::run($res !== false ? 1 : 0);
        }
        if (isset($param['find'])) {
            $res = Db::name('third_configs_web')->where("tc_id", $param['id'])->find();
            if (empty($res['icon']) || $res['icon'] == "/static/admin/img/add.png") {
                $res['icon'] = $sys['game_default_pic'];
            }
            return Respons::run(1, $res);
        }
    }

    public function third_export(Request $request)
    {
        $id = $request->param('tc_id/d');
        $config = Db::name('third_configs')->where("tc_id", $id)->find();
        if (empty($config)) {
            return $this->error('平台信息不存在');
        }
        $apis = Db::name('game_apis')->where("ga_tcid", $id)->select();
        $data = [
            'config' => $config,
            'apis' => $apis,
        ];
        return $this->success([
            'data' => base64_encode(json_encode($data)),
            'json' => $data
        ]);
    }


    public function third_import(Request $request)
    {
        $data = json_decode(base64_decode($request->param('data/s')), true);
        if (empty($data) || !isset($data['config']) || !isset($data['apis'])) {
            return $this->error('数据有误', ['data' => $data]);
        }

        $config = Db::name('third_configs')->where("tc_code", $data['config']['tc_code'])->find();
        if (!empty($config)) {
            return $this->error('平台信息重复');
        }
        $configData = $data['config'];
        $configData['tc_status'] = 0;
        unset($configData['tc_id']);
        $tc_id = Db::name('third_configs')->insertGetId($configData);
        if ($tc_id) {
            foreach ($data['apis'] as $api) {
                $apiData = $api;
                unset($apiData['ga_id']);
                $apiData['ga_tcid'] = $tc_id;
                Db::name('game_apis')->insertGetId($apiData);
            }
        }
        return $this->success();
    }

    public function user_stat()
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $where[] = ["su_dt", ">=", Utils::getQueryDate($time[0], "Ymd")];
            $where[] = ["su_dt", "<=", Utils::getQueryDate($time[1], "Ymd")];
        }

        if (empty($param['username'])) {
            return $this->error('请输入用户信息');
        }
        $lock = userLock($param['username'], 'user_stat');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        $where_field = is_numeric($param['username']) ? 'id|username|mail|phone|rec_code' : 'username|mail|phone';
        $uid = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
        if (empty($uid)) {
            return $this->error('请输入正确用户信息');
        }
        $defaultData = [
            'bet_num' => 0,
            'bet_amount' => 0,
            'bet_win_num' => 0,
            'bet_win_amount' => 0,
            'bet_valid_amount' => 0,
            'user_win' => 0,
            'bet_draw_num' => 0,
            'bet_draw_amount' => 0,
            'bet_num_rate' => '',
        ];
        $where[] = ["su_uid", "=", $uid];
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $tc_stat = Db::name($table['_stats_global'])->where($where)
            ->where('su_tcid', 'not null')   //平台不能为空
            ->field("su_tcid, SUM(su_betnum) AS bet_num, SUM(su_bet) AS bet_amount,
            sum(su_winnum) as bet_win_num,
            sum(su_win) as bet_win_amount, 
            SUM(su_valid_bet) as bet_valid_amount, 
            SUM(su_syswin*-1) as user_win, 
            sum(su_drawnum) as bet_draw_num,
            sum(su_draw) as bet_draw_amount")
            ->group("su_tcid")->select();
        if (!$tc_stat->isEmpty()) {
            $thirds = Db::name('third_configs')->column('tc_name', 'tc_id');
            $total = $defaultData;
            $total['tc_name'] = '总计';
            foreach ($tc_stat as $v) {
                foreach ($defaultData as $key => $val) {
                    if (in_array($key, ['tc_name', 'bet_num_rate'])) {
                        continue;
                    }
                    $total[$key] = round($total[$key] + $v[$key], 2);
                }
            }
            $tc_stat = $tc_stat->each(function ($item) use ($thirds, $total) {
                $item['tc_name'] = $thirds[$item['su_tcid']] ?? $item['su_tcid'];
                $item['bet_num_rate'] = sprintf4f($item['bet_num'] / $total['bet_num'] * 100) . '%';
                return $item;
            });
            $tc_stat[] = $total;
        }
        $game_type = Db::name('game_type')->column('name', 'id');
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $game_stat = Db::name($table['_stats_global'])->where($where)
            ->where('su_tcid', 'not null')   //平台不能为空
            ->field("su_gtype, SUM(su_betnum) AS bet_num, SUM(su_bet) AS bet_amount,
            sum(su_winnum) as bet_win_num,
            sum(su_win) as bet_win_amount, 
            SUM(su_valid_bet) as bet_valid_amount, 
            SUM(su_syswin*-1) as user_win, 
            sum(su_drawnum) as bet_draw_num,
            sum(su_draw) as bet_draw_amount")
            ->group("su_gtype")->select();
        if (!$game_stat->isEmpty()) {
            $total = $defaultData;
            $total['type_name'] = '总计';
            foreach ($game_stat as $v) {
                foreach ($defaultData as $key => $val) {
                    if (in_array($key, ['type_name', 'bet_num_rate'])) {
                        continue;
                    }
                    $total[$key] = round($total[$key] + $v[$key], 2);
                }
            }
            $game_stat = $game_stat->each(function ($item) use ($game_type, $total) {
                $item['type_name'] = $game_type[$item['su_gtype']] ?? $item['su_gtype'];
                $item['bet_num_rate'] = sprintf4f($item['bet_num'] / $total['bet_num'] * 100) . '%';
                return $item;
            });
        }
        $result = [];
        $result['data'] = $tc_stat;
        $result['game_stat'] = $game_stat;
        $result['code'] = 1;
        return Respons::run($result);
    }


    public function game_stat()
    {
        $param = $this->request->param();
        $where = [];
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
        } else {
            $time = [date("Y-m-d"), date("Y-m-d")];
        }
        $start_time = Utils::getQueryDateTime($time[0]);
        $end_time = Utils::getQueryDateTime($time[1], true);
        $where[] = ["su_dt", ">=", date("Ymd", $start_time)];
        $where[] = ["su_dt", "<=", date("Ymd", $end_time)];
        if (!empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|phone' : 'username|mail|phone';
            $uid = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["su_uid", "=", $uid];
        }
        if (isset($param['tc_id']) && $param['tc_id'] > 0) {
            $where[] = ["su_tcid", "=", $param['tc_id']];
        }

        if (isset($param['gid']) && $param['gid'] != '') {
            if (!is_numeric($param['gid'])) {
                $param['gid'] = GameInfosModel::where("gi_name", "LIKE", "%{$param['gid']}%")->value('gi_gid');
            }
            $where[] = ["su_gid", "=", $param['gid']];
        }
        if (isset($param['g_type']) && $param['g_type'] > 0) {
            $where[] = ["su_gtype", "=", $param['g_type']];
        }
        $lock = userLock($this->user_id, 'game_stat');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        $groupbys = ["su_gtype", "su_tcid", "su_gid"];
        $stat_type = isset($param['stat_type']) && in_array($param['stat_type'], [0, 1, 2]) ? $param['stat_type'] : 0;

        if (isset($param['order']) && $param['order'] != '') {
            $order = '' . $param['field'] . ' ' . ($param['order'] == 'DESC' ? 'DESC' : 'ASC');
        } else {
            $order = 'profit desc';
        }

        $thirds = Db::name('third_configs')->column('tc_name', 'tc_id');
        $game_names = GameInfosModel::column('gi_name', 'gi_gid');
        $game_types = GameTypeModel::column('name', 'id');
        $field = "su_gid, su_tcid, su_gtype, SUM(su_betnum) AS bet_num, SUM(su_bet) AS bet_amount,
            sum(su_winnum) as bet_win_num,sum(su_win) as bet_win_amount, 
            SUM(su_valid_bet) as bet_valid_amount, 
            SUM(su_draw) as bet_draw_amount, 
            count(distinct su_uid) as user_count, 
            SUM(su_syswin) as profit";
        $page = $this->request->param('page/d', 1);
        $size = $this->request->param('limit/d', 10);
        $offset = ($page - 1) * $size;
        //获取对应时区表
        $table = BetService::getThirdStatsTable('');
        $game_stat = Db::table($table['stats_global'])->where($where)
            ->field($field)
            ->where('su_tcid', 'not null')   //平台不能为空
            ->group($groupbys[$stat_type])->limit($offset, $size)->order($order)->select();
        $total = Db::table($table['stats_global'])->where($where)
            ->field($field)
            ->where('su_tcid', 'not null')   //平台不能为空
            ->group($groupbys[$stat_type])->count();
        $game_stat_count = [];
        $game_stat_count['type_name'] = lang('合计');
        $game_stat_count['tc_name'] = $game_stat_count['type_name'];
        $game_stat_count['bet_num'] = 0;
        $game_stat_count['bet_amount'] = 0;
        $game_stat_count['user_num'] = 0;
        $game_stat_count['bet_valid_amount'] = 0;
        $game_stat_count['bet_win_amount'] = 0;
        $game_stat_count['profit'] = 0;
        if (!$game_stat->isEmpty()) {
            $game_stat = $game_stat->each(function ($item) use ($game_names, $thirds, $game_types, $stat_type, $where, $table, &$game_stat_count) {
                $item['game_name'] = $game_names[$item['su_gid']] ?? $item['su_gid'];
                $item['tc_name'] = $thirds[$item['su_tcid']] ?? $item['su_tcid'];
                $item['type_name'] = $game_types[$item['su_gtype']] ?? $item['su_gtype'];

                $userNum = 0;
                switch ($stat_type) {
                    case 0:
                        //游戏类型
                        $userNum = Db::table($table['stats_global'])
                            ->where($where)->where('su_gtype', '=', $item['su_gtype'])
                            ->group('su_uid')->count();
                        break;
                    case 1:
                        //游戏平台
                        $userNum = Db::table($table['stats_global'])
                            ->where($where)->where('su_tcid', '=', $item['su_tcid'])
                            ->group('su_uid')->count();
                        break;
                    case 2:
                        //子游戏
                        $userNum = Db::table($table['stats_global'])
                            ->where($where)->where('su_gid', '=', $item['su_gid'])
                            ->group('su_uid')->count();
                        unset($game_stat_count['type_name']);
                        break;
                }
                $item['user_num'] = $userNum;
                $game_stat_count['bet_num'] += $item['bet_num'];
                $game_stat_count['bet_amount'] += $item['bet_amount'];
                $game_stat_count['user_num'] += $item['user_num'];
                $game_stat_count['bet_valid_amount'] += $item['bet_valid_amount'];
                $game_stat_count['bet_win_amount'] += $item['bet_win_amount'];
                $game_stat_count['profit'] += $item['profit'];
                return $item;
            });
        }
        $game_stat_count['bet_amount'] = sprintf("%.2f", $game_stat_count['bet_amount']);
        $game_stat_count['bet_valid_amount'] = sprintf("%.2f", $game_stat_count['bet_valid_amount']);
        $game_stat_count['bet_win_amount'] = sprintf("%.2f", $game_stat_count['bet_win_amount']);
        $game_stat_count['profit'] = sprintf("%.2f", $game_stat_count['profit']);
        $game_stat[] = $game_stat_count;
        return $this->success([
            'game_third' => Utils::getOptions('third_configs', ['tc_id', 'tc_name']),
            'game_type' => Utils::getOptions('game_type', ['id', 'name']),
            'data' => $game_stat,
            'total' => $total,
        ]);
    }

    public function user_report(\think\Request $request)
    {
        $rsps = (new GameFactory($request->token->uid))->user_report();
        return $rsps;
    }

    public function report(\think\Request $request)
    {
        $act = $request->param('act', 'report');
        if ($act == 'detail') {
            $rsps = (new GameFactory($this->user_id))->report_detail();
        } else {
            $rsps = (new GameFactory($this->user_id))->report();
        }
        return $rsps;
    }

    public function back(\think\Request $request)
    {
        $rsps = (new GameFactory($request->token->uid))->back();
        return $rsps;
    }

    //拒绝
    public function refused(Request $request)
    {
        $param = $request->param();
        $id = $param['id'];
        if (isset($param['edit']) && $id) {
            $CommomRedis = new CommomRedis();
            if (!$CommomRedis->lock('agent_fyfy_' . $id)) {
                return Respons::run(0, [], lang('请勿频繁访问'));
            }
            return $this->publicRefused($id);
        }
    }

    //公共拒绝方法
    private function publicRefused($id)
    {
        //先修改状态
        $where = [];
        $where[] = ["sb_id", "=", $id];
        $data = Db::name('third_stats_back')->where($where)->find();
        //判断状态是否未处理
        if ($data['sb_payed'] == 1) {
            //sb_payed
            $res = Db::name('third_stats_back')->update(['sb_payed' => 0, 'sb_id' => $id, 'sb_paytime' => time()]);
            UserAdminLogModel::add_log($data['sb_uid'], "游戏返水[" . $id . "]拒绝, 金额: {$data['sb_backmoney']}", Session::get("adminInfo.name"));
            return Respons::run($res ? 1 : 0);
        }
    }

    //派送
    public function send(Request $request)
    {
        $param = $request->param();
        $id = $param['id'];
        if (isset($param['edit']) && $id) {
            $CommomRedis = new CommomRedis();
            if (!$CommomRedis->lock('agent_back_' . $id)) {
                return Respons::run(0, [], lang('请勿频繁访问'));
            }
            return $this->publicSend($id);
        }
    }

    //公共派送状态
    private function publicSend($id)
    {
        $where = [];
        $where[] = ["sb_id", "=", $id];
        $data = Db::name('third_stats_back')->where($where)->find();

        //判断状态是否未处理
        if ($data['sb_payed'] == 1) {
            Db::startTrans();
            try {
                //先修改状态
                $upd['sb_payed'] = 2;
                $upd['sb_id'] = $id;
                $upd['sb_paytime'] = time();
                $res = Db::name('third_stats_back')->update($upd);
                if ($data['sb_backmoney'] > 0) {
                    //修改余额
                    $UserModel = new UserModel();
                    $UserModel->cz_balance($data['sb_uid'], $data['sb_backmoney'], '+', true, '游戏返水', 5, 0, 1, $id, true, 101);
                }
                UserAdminLogModel::add_log($data['sb_uid'], "游戏返水[" . $id . "]派送, 金额: {$data['sb_backmoney']}", Session::get("adminInfo.name"));
                // 提交事务
                Db::commit();
                return Respons::run($res ? 1 : 0);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    //一键派发
    public function all_send(Request $request)
    {
        $param = $request->param();
        $ids = explode(',', $param['id']);
        foreach ($ids as $id) {
            $this->publicSend($id);
        }
        return Respons::run(1);
    }

    //一键拒绝
    public function all_refused(Request $request)
    {
        $param = $request->param();
        $ids = explode(',', $param['id']);
        foreach ($ids as $id) {
            $this->publicRefused($id);
        }
        return Respons::run(1);
    }

    /**
     * 批量操作选中游戏状态
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DbException
     */
    public function switch_game(Request $request)
    {
        $param = $request->param();
        $state = $param['state'];
        $map = [
            //未锁定是游戏才能批量修改状态
            'g_lock' => 0
        ];
        Db::name('games')->whereIn('g_id', $param['id'])->where($map)->update([
            'g_state' => $state
        ]);
        $g_state_text = ['关闭', '开启', '维护'];
        Utils::addOptLog("状态:[{$g_state_text[$state]}]" . ";选中g_id:{" . json_encode($param['id']) . "}", '', 'qd_games', '选择游戏状态修改');
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        return Respons::run(1);
    }

    public function clear(Request $request)
    {
        $param = $request->param();
        $key = $param['key'];
        $CommomRedis = new CommomRedis();
        if ($key == 'all') {
            $CommomRedis->clearTag((new GameFactory())->tag);
        } else {
            $CommomRedis->delete($key);
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    /**
     * 导出
     * @param Request $request
     * @return void
     */
    public function export(Request $request)
    {
        $g_tcid = $request->param('g_tcid/d');
        $g_type = $request->param('g_type/d');
        if (empty($g_tcid) || empty($g_type)) {
            return $this->error('请选择游戏平台和游戏分类');
        }
        $list = Db::name('games')->alias('a')->join('game_infos b', 'a.g_id=b.gi_gid')->where(['g_tcid' => $g_tcid, 'g_type' => $g_type, 'g_is_del' => 0])->select();
        if ($list->isEmpty()) {
            return $this->error('没有可导出的游戏数据');
        }
        $tc_name = Db::name('third_configs')->where('tc_id', $g_tcid)->value('tc_name');
        $type_name = Db::name('game_type')->where('id', $g_type)->value('name');
        $header = [
            'g_code' => '游戏代码:g_code',
            'g_tag' => '三方平台的代码:g_tag',
            'g_icon' => '游戏图标:g_icon',
            'g_state' => '游戏状态:g_state',
            'g_type' => '游戏分类ID:g_type',
            'g_sort' => '排序:g_sort',
            'g_hot' => '热门:g_hot',
            'g_sports' => '运动:g_sports',
            'g_backstatus' => '参与返水:g_backstatus',
            'g_fystatus' => '参与返佣:g_fystatus',
            'g_rtp' => '胜率:g_rtp',
            'label' => '关键词:label',
            'g_show' => '显示:g_show',
            'g_exfield' => '扩展:g_exfield',
            'g_keyword' => '关键词:g_keyword',
            'g_is_new' => '最新:g_is_new',
            'g_is_optimum' => '最佳:g_is_optimum',
            'g_is_self' => '自营:g_is_self',
            'gi_headimg' => '游戏封面:gi_headimg',
            'gi_name' => '游戏名称:gi_name',
            'gi_thirdlang' => '三方语言:gi_thirdlang',
            'gi_info' => '游戏介绍:gi_info',
            'gi_content' => '游戏内容:gi_content',
        ];
        $file_name = genCsv($header, $list, "{$tc_name}-{$type_name}-" . date('YmdHis', time()), false);
        $s3_url = Uploads::uploadS3(public_path() . $file_name);
        Utils::addOptLogByHandle('导出三方游戏');
        return $this->success([
            'data' => ['url' => $s3_url ?: toMedia($file_name, '/')]
        ]);
    }

    /**
     * 导入
     * @param Request $request
     * @return void
     */
    public function import(Request $request)
    {
        $file = $request->param('file/s');
        if (!file_exists(toMedia($file, '', 2))) {
            return Respons::run(0, '', '请上传文件');
        }
        $g_tcid = $request->param('g_tcid/d');
        $g_web_tcid = $request->param('g_web_tcid/d', 0);
        $g_type = $request->param('g_type/d');
        if (empty($g_tcid) || empty($g_type)) {
            return $this->error('请选择游戏平台和游戏分类');
        }
        $tc = Db::name('third_configs')->where('tc_id', $g_tcid)->find();
        if (empty($tc)) {
            return $this->error('请选择游戏平台');
        }
        $type = Db::name('game_type')->where('id', $g_type)->find();
        if (empty($type)) {
            return $this->error('请选择游戏分类');
        }
        $strs = explode("\n", file_get_contents(toMedia($file, '', 2)));
        $dataAll = [];
        if (isset($strs[0]) && $strs[0]) {
            $keys = explode(",", $strs[0]);
            for ($i = 1; $i < count($strs); $i++) {
                if (!isset($strs[$i]) || !$strs[$i]) {
                    continue;
                }
                $row = [];
                $vals = explode(",", $strs[$i]);
                foreach ($keys as $j => $key) {
                    list($name, $field) = explode(":", $key);
                    $_value = trim($vals[$j], "\"\t");
                    $_value = str_replace("\r", "", $_value);
                    if (strexists(trim($field), "gi_")) {
                        $row['game_infos'][trim($field)] = $_value;
                    } else {
                        $value = trim($vals[$j], "\"\t");
                        $row[trim($field)] = trim($value);#去掉空格;
                    }
                }
                $row['g_tcid'] = $g_tcid;
                $row['g_web_tcid'] = $g_web_tcid ?: $g_tcid;
                $row['g_type'] = $g_type;
                $game_info = Db::name('games')->where(['g_tcid' => $g_tcid, 'g_code' => $row['g_code'], 'g_is_del' => 0])->find();
                if (!empty($game_info)) {
                    Db::name('games')->where('g_id', $game_info['g_id'])->update(['g_icon' => $row['g_icon'], 'g_sort' => $row['g_sort']]);
                    Db::name('game_infos')->where('gi_gid', $game_info['g_id'])->update(['gi_headimg' => $row['g_icon']]);
                    continue;
                }
                $dataAll[] = $row;
            }
        }

        if (empty($dataAll)) {
            return $this->error('文件数据为空或游戏重复导入');
        }
        foreach ($dataAll as $data) {
            $game_infos = $data['game_infos'] ?? [];
            unset($data['game_infos']);
            $gid = Db::name('games')->insertGetId($data);
            if ($gid) {
                $game_infos['gi_gid'] = $gid;
                $game_infos['gi_lang'] = "CN";
                Db::name('game_infos')->insertGetId($game_infos);
                Db::name('game_configs')->insertGetId(["gc_gid" => $gid]);
            }
        }
        return $this->success(['data' => $dataAll]);
    }

    public function apis()
    {
        $tc_id = $this->request->param('tc_id/s');
        $apis = GameApis::where('ga_tcid', $tc_id)->select();
        foreach ($apis as $k => $val) {
            $apis[$k]['ga_keyfield'] = json_decode($val['ga_keyfield'], true);
            $apis[$k]['ga_hparam'] = json_decode($val['ga_hparam'], true);
            $apis[$k]['ga_pparam'] = json_decode($val['ga_pparam'], true);
        }

        return $this->success([
            'data' => $apis
        ]);
    }

    //添加
    public function apis_add()
    {
        if ($this->request->has('add')) {
            $info = $this->request->param('info/a');
            try {
                validate(\app\common\validate\GameApiValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return $this->error($e->getError());
            }
            $ga_id = $info['ga_id'] ?? 0;
            unset($info['ga_id']);
            if ($ga_id > 0) {
                $api = GameApis::where('ga_id', $ga_id)->find();
                if (empty($api)) {
                    return $this->error('数据不存在');
                }
                foreach ($info as $k => $v) {
                    $info[$k] = htmlspecialchars_decode($v);
                }
                Utils::addOptLog($api->toJson(), json_encode($info), 'game_apis', '修改三方平台API配置');
                GameApis::where('ga_id', $ga_id)->update($info);
            } else {
                Utils::addOptLog(json_encode($info), '', 'game_apis', '添加三方平台API配置');
                GameApis::create($info);
            }
            //获取平台信息
            $trdcode = ThirdConfigs::where('tc_id', '=', $info['ga_tcid'])->value('tc_code');
            $cfg = Games::getConfigs(null, $info['ga_tcid']);
            Redis::hset('trdcfgs', $trdcode, json_encode($cfg));
            ThirdConfigs::clearPlatformConfigCache($trdcode);
        }
        if ($this->request->has('find')) {
            $id = $this->request->param('id');
            $model = new GameApis();
            $res = $model->where("ga_id", $id)->find();
            if (empty($res)) {
                return $this->error('数据不存在');
            }
            return $this->success(['data' => $res->getData()]);
        }
        return $this->success();
    }

    public function rtpList(Request $request)
    {
        return (new GameFactory())->getRtpList();
    }

    public function rtpEdit(Request $request)
    {
        return (new GameFactory())->rtpEdit();
    }

    public function rtpAdd(Request $request)
    {
        return (new GameFactory())->rtpAdd();
    }
}
