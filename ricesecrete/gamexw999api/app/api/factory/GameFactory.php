<?php

namespace app\api\factory;

use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Third;
use app\common\lib\TokenCheck;
use app\common\service\AgentService;
use app\common\service\BetService;
use app\common\service\GameService;
use app\common\service\MemberService;
use app\model\Games;
use app\model\ThirdConfigs;
use app\model\UserGames;
use app\model\UserThirds;
use app\common\model\{BangdanModel,
    DefaultHeadModel,
    GameSetModel,
    GamesModel,
    GameTypeModel,
    GameTicketModel,
    GameTxModel,
    GameUserModel,
    GameZhudanModel,
    UserCollectModel,
    UserModel,
    GameBackConfigModel,
    GameReportOperatingModel};
use think\facade\{Config, Request, Db, Log};
use app\common\game\Entry;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use think\Collection;
use think\Exception;

class GameFactory
{
    use GameZhuDanFactory;
    use GameTxFactory;

    private $param;//请求参数
    private $header;//请求头参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->header = Request::header();
        $this->uid = $uid;
    }

    private function sys_sign_type($key)
    {
        $arr = [
            'Fundist' => '飞行游戏',
            'Sport' => '正波',
            'Bsgame' => 'Bs平台',
            'FB' => 'FB体育',
        ];
        return $arr[$key];
    }

    //三方游戏首页
    public function index_run()
    {
        $param = $this->param;
        $limit = $this->param['limit'] ?? 3;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $GameTypeModel = app()->make(GameTypeModel::class);
        $data = $GameTypeModel->where(['state' => 1])->order('sort desc, id desc')->field('name, id, sort, icon')->select();
        foreach ($data as &$val) {
            if ($val['icon'] == '/static/admin/img/add.png') {
                $val['icon'] = $sys['game_default_pic'];
            }
            $val['game'] = GameSetModel::field('id,game_sys_sign,game_name,game_status,sort,icon,wallet_type,tc_id')
                ->where(['type' => $val['id']])
                ->where([['game_status', '>', 0], ['is_hot', '=', 0]])
                ->limit($limit)
                ->order('sort desc')
                ->filter(function ($data) use ($sys) {
                    if ($data['icon'] == '/static/admin/img/add.png') {
                        $data['icon'] = $sys['game_default_pic'];
                    }
                })
                ->select();
        }

        $rsps['code'] = 1;
        $rsps['hot'] = GameSetModel::field('id,game_sys_sign,game_name,game_status,sort,icon,wallet_type,tc_id')
            ->where([['game_status', '>', 0], ['is_hot', '=', 1]])
            ->limit($limit)
            ->order('sort desc')
            ->filter(function ($data) use ($sys) {
                if ($data['icon'] == '/static/admin/img/add.png') {
                    $data['icon'] = $sys['game_default_pic'];
                }
            })
            ->select();

        $rsps['data'] = $data;
        return $rsps;
    }

    //三方游戏首页
    public function index1_run()
    {
        $limit = $this->param['limit'] ?? 3;
        $nlimit = $this->param['nlimit'] ?? 12;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $GameTypeModel = app()->make(GameTypeModel::class);
        $wherestr = '';
        if (!empty($lang)) {
            $wherestr = 'c.country not like "%' . $lang . '%" or c.country is null';
        }
        $data = $GameTypeModel->where(['state' => 1])->order('sort desc, id desc')->field('name, id, sort, icon')->select();
        foreach ($data as &$val) {
            if ($val['icon'] == '/static/admin/img/add.png') {
                $val['icon'] = $sys['game_default_pic'];
            }
            $val['game'] = Db::name('games')
                ->alias('a')
                ->join('game_infos i', 'a.g_id = i.gi_gid')
                ->join('third_configs c', 'a.g_tcid = c.tc_id')
                ->where([['g_state', '>', 0], ['g_hot', '=', 0], ['g_show', '=', 1], ['g_is_del', '=', 0], ['g_type', '=', $val['id']]])
                ->where($wherestr)
                ->limit($limit)
                ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_tcid,a.g_state as game_status,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code')
                ->filter(function ($data) use ($sys) {
                    if ($data['icon'] == '/static/admin/img/add.png') {
                        $data['icon'] = $sys['game_default_pic'];
                    }
                    if ($data['gi_headimg'] == '/static/admin/img/add.png') {
                        $data['gi_headimg'] = $sys['game_default_pic'];
                    }
                    return $data;
                })
                ->order("a.g_sort desc,a.g_id desc")
                ->select();
        }

        $rsps['code'] = 1;
        $rsps['hot'] = Db::name('games')
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->where([['g_state', '>', 0], ['g_show', '=', 1], ['g_is_del', '=', 0], ['g_hot', '=', 1]])
            ->where($wherestr)
            ->limit($nlimit)
            ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_tcid,a.g_state as game_status,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code')
            ->order("a.g_sort desc, a.g_id desc")
            ->filter(function ($data) use ($sys) {
                if ($data['icon'] == '/static/admin/img/add.png') {
                    $data['icon'] = $sys['game_default_pic'];
                }
                if ($data['gi_headimg'] == '/static/admin/img/add.png') {
                    $data['gi_headimg'] = $sys['game_default_pic'];
                }
                return $data;
            })
            ->select();

        $rsps['data'] = $data;
        return $rsps;
    }

    //三方游戏首页2
    public function index2_run()
    {
        $limit = $this->param['nlimit'] ?? 11;
        $return = [];
        $return['code'] = 1;
        $data = [];
        $data['self'] = [
            'name' => 'In-House Games',
            'icon' => 'icon-inhouse',
            'game' => $this->getGameListByMark(4, $limit)
        ];
        $data['top'] = [
            'name' => 'Top',
            'icon' => 'icon-top',
            'game' => $this->getGameListByMark(2, $limit)
        ];
        $data['hot'] = [
            'name' => 'Hot',
            'icon' => 'icon-fire',
            'game' => $this->getGameListByMark(1, $limit),
        ];
        $data['new'] = [
            'name' => 'New',
            'icon' => 'icon-new',
            'game' => $this->getGameListByMark(3, $limit)
        ];

        $return['data'] = $data;
        return $return;
    }

    /**
     * @param int $is 1 热门 2最佳 3最新 4内部, 字符串的话直接为查询条件
     * @param int $limit 获取游戏条数
     * @return array
     */
    private function getGameListByMark($is = 1, $limit = 11)
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [
            ['g_state', '>', 0],
            ['g_show', '=', 1],
            ['g_is_del', '=', 0]
        ];
        $whereStr = '';
        if (is_string($is)) {
            $whereStr = $is;
        } else {
            if ($is == 1) {
                $where[] = ['g_hot', '=', 1];
            } elseif ($is == 2) {
                $where[] = ['g_is_optimum', '=', 1];
            } elseif ($is == 3) {
                $where[] = ['g_is_new', '=', 1];
            } else {
                $where[] = ['g_is_self', '=', 1];
            }
        }

        $countryStr = '';
        if (!empty($lang)) {
            $countryStr = 'c.country not like "%' . $lang . '%" or c.country is null';
        }
        $uid = 0;
        //如果登录就获取用户uid，不抛出异常（用于判断用户是否收藏此游戏）
        try {
            $header = request()->header();
            $token = TokenCheck::run($header, []);
            $uid = $token->uid;
        } catch (\Exception $e) {
        }
        $list = Db::name('games')
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_web_tcid = c.tc_id')
            ->where($where)
            ->where($whereStr)
            ->where($countryStr)
            ->limit($limit)
            ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_tcid,a.g_state as game_status,a.g_is_self,a.g_exfield,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code,c.tc_name,a.label,c.is_new_tab,a.g_cj as wallet_type')
            ->order("a.g_sort desc, a.g_id desc")
            ->filter(function ($data) use ($sys, $uid) {
                if ($data['icon'] == '/static/admin/img/add.png') {
                    $data['icon'] = $sys['game_default_pic'] ?? '';
                }
                if ($data['gi_headimg'] == '/static/admin/img/add.png') {
                    $data['gi_headimg'] = $sys['game_default_pic'] ?? '';
                }
                //判断是否收藏
                $data['is_collect'] = CollectFactory::judgeIsCollect(UserCollectModel::GAME_TYPE, $uid, $data['id']);

                $data['lottery'] = GameService::getLotteryInfo($data['id']);
                return $data;
            })
            ->select();

        return $list;
    }

    //获取游戏分类
    public function get_game_type()
    {
        /* @var GameTypeModel $GameTypeModel */
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $GameTypeModel = app()->make(GameTypeModel::class);
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $wherestr = '';
        if (!empty($lang)) {
            $wherestr = 'c.country not like "%' . $lang . '%" or c.country is null';
        }
        $redis_key = "get_game_type:{$lang}";
        $data = $ApiRedis->get($redis_key);
        if (!$data) {
            $data = $GameTypeModel->alias('a')->where(['a.state' => 1])
                ->join('games i', 'a.id=i.g_type and i.g_state=1 and i.g_show=1')
                ->join('third_configs c', 'i.g_tcid = c.tc_id')
                ->where($wherestr)
                ->order('sort desc, id desc')
                ->field('a.name, a.id, a.sort, a.icon, a.default_icon,count(i.g_id) game_count')
                ->group('a.id')
                ->filter(function ($data) use ($sys) {
                    if ($data['icon'] == '/static/admin/img/add.png') {
                        $data['icon'] = $sys['game_default_pic'];
                    }
                })
                ->select();
            $ApiRedis->set($redis_key, $data, 180);
        }
        $rsps['code'] = 1;
        $rsps['data'] = $data;
        $rsps['tags'] = self::getTags(null);
        return $rsps;
    }

    //获取真人视讯平台的小费注单
    public function get_special_logs($start, $end)
    {
        //wm时区是+8的
        date_default_timezone_set('Asia/Shanghai');
        $flags = [
            "start" => $start,
            "endtime" => $end,
        ];
        $ThirdConfigs = app()->make(ThirdConfigs::class);
        $tc_id = $ThirdConfigs->where(['tc_code' => 'WM'])->value('tc_id');
        //只传平台id 不传游戏id
        if (!$tc_id) {
            echo '平台不存在';
            return false;
        }
        $apicfgs = Games::getConfigs('', $tc_id);
        return Third::run($apicfgs, 'get_special_logs', $flags); //注单获取的开始时间结束时间等在在otherkvs中指定
    }

    //获取游戏分类
    public function get_game_third()
    {
        $type = $this->param['type'] ?? 0;

        $where[] = ['tc_status', '=', 1];
        if ($type) {
            //根据游戏类型获取该类型下面所有厂商
            $tcidArr = Games::where('g_type', '=', $type)->group('g_web_tcid')->column('g_web_tcid');
            if ($tcidArr) {
                $where[] = ['tc_id', 'in', $tcidArr];
            }
        }


        $game_number = [];
        $data = [];
        try {
            $data = Db::name('third_configs_web')->where($where)->order('sort desc, tc_id desc')->field('tc_name, tc_id, sort, simple_icon, icon')->select()->toArray();
            $game_number = Db::name('games')->where('g_state', '>', 0)->where('g_show', 1)
                ->field('g_web_tcid, count(*) AS total')
                ->group("g_web_tcid")
                ->select()->column('total', 'g_web_tcid');
        } catch (\Exception $e) {
        }

        foreach ($data as $k => $row) {
            $data[$k]['game_number'] = $game_number[$row['tc_id']] ?? 0;
        }
        $result = [];
        $result['code'] = 1;
        $result['data'] = $data;
        return $result;
    }


    public function login_threee_game()
    {
        $gid = $this->param['gid'] ?? 0;
        $user = UserModel::where("id", $this->uid)->field('lebal,tree')->find();
        if ($user['lebal'] != 1) {
            ajax_return("测试人员不可进入游戏", 0);
        }
        if (MemberService::tagBan($this->uid, 'is_game')) {
            ajax_return("禁止游戏，请联系客服", 0);
        }
        $games = Db::name("games")->where(['g_id' => $gid])->field('g_id, g_tcid, g_type, g_state')->find();
        if (empty($games)) {
            ajax_return("game doesn't exist", 0);
        }

        if ($games['g_state'] != 1) {
            ajax_return("游戏待开放", 0);
        }

        //获取平台禁用代理信息
        $thirdConfig = Db::name('third_configs')->where('tc_id', '=', $games['g_tcid'])->field('tc_disable_agent_ids')->find();
        if (!$thirdConfig) {
            ajax_return("game doesn't exist", 0);
        }
        if ($thirdConfig['tc_disable_agent_ids']) {
            $disableAgentIdsArr = explode(',', $thirdConfig['tc_disable_agent_ids']);
            foreach ($disableAgentIdsArr as $v) {
                //循环判断禁用代理是否在用户tree中，存在则用户禁止进入
                if (strexists($user['tree'], '/' . $v . '/')) {
                    ajax_return("禁止进入该游戏, 请联系客服!", 0);
                }
            }
        }

        $tInfo = Db::name("game_type")->where(['id' => $games['g_type']])->field('min_amount, max_amount')->find(); // 分类详细
        if ($tInfo['min_amount'] > 0 || $tInfo['max_amount'] > 0) {
            $balance = UserModel::where(['id' => $this->uid])->value('balance');
            if ($tInfo['min_amount'] > 0 && $balance < $tInfo['min_amount']) {
                ajax_return("余额小于进入游戏的最低限额", 0);
            }

            if ($tInfo['max_amount'] > 0 && $balance > $tInfo['max_amount']) {
                ajax_return("余额大于进入游戏的最高限额", 0);
            }
        }

        $other['platform'] = $this->param['platform'] ?? 0;
        $other['back_url'] = $this->param['back_url'] ?? '';
        $other['depositMoney'] = $this->param['amount'] ?? 0;
        $data = \app\common\FastGame\Base\Game::getGameUrl($gid, $this->uid, true, $other);
        //增加游玩游戏次数
        UserGames::where([['ug_tcid', '=', $games['g_tcid']], ['ug_gid', '=', $gid], ['ug_uid', '=', $this->uid]])->inc('ug_play_count', 1)->update(
            ['ug_lastupdate' => time()]);

        $datas = json_decode($data->getContent(), true);
        $rsps['code'] = 0;
        $rsps['msg'] = $datas['msg'] ?? '';
        if ($datas['code'] == "00000") {
            $rsps['code'] = 1;
            $rsps['data'] = $datas['data']['url'];
            $rsps['tcid'] = $games['g_tcid'];
            $rsps['three_amount'] = Game::getThirdBalance($games['g_tcid'], $this->uid) ?: 0;
        }
        return $rsps;
    }


    public function login_luckysport_game()
    {
        //获取平台，写死平台标识
        $tcDetail = ThirdConfigs::where('tc_code', '=', 'luckysport')->find();
        $user = UserModel::where("id", $this->uid)->field('lebal,tree')->find();
        if ($user['lebal'] != 1) {
            ajax_return("测试人员不可进入游戏", 0);
        }
        if (MemberService::tagBan($this->uid, 'is_game')) {
            ajax_return("禁止游戏，请联系客服", 0);
        }
        //获取该平台下随便一个游戏id
        $games = Db::name("games")->where(['g_tcid' => $tcDetail->tc_id])->field('g_id, g_tcid, g_type, g_state')->find();
        if (empty($games)) {
            ajax_return("game doesn't exist", 0);
        }
        $gid = $games['g_id'];

        if ($games['g_state'] != 1) {
            ajax_return("游戏待开放", 0);
        }

        //获取平台禁用代理信息
        $thirdConfig = Db::name('third_configs')->where('tc_id', '=', $games['g_tcid'])->field('tc_disable_agent_ids')->find();
        if (!$thirdConfig) {
            ajax_return("game doesn't exist", 0);
        }
        if ($thirdConfig['tc_disable_agent_ids']) {
            $disableAgentIdsArr = explode(',', $thirdConfig['tc_disable_agent_ids']);
            foreach ($disableAgentIdsArr as $v) {
                //循环判断禁用代理是否在用户tree中，存在则用户禁止进入
                if (strexists($user['tree'], '/' . $v . '/')) {
                    ajax_return("禁止进入该游戏, 请联系客服!", 0);
                }
            }
        }

        $tInfo = Db::name("game_type")->where(['id' => $games['g_type']])->field('min_amount, max_amount')->find(); // 分类详细
        if ($tInfo['min_amount'] > 0 || $tInfo['max_amount'] > 0) {
            $balance = UserModel::where(['id' => $this->uid])->value('balance');
            if ($tInfo['min_amount'] > 0 && $balance < $tInfo['min_amount']) {
                ajax_return("余额小于进入游戏的最低限额", 0);
            }

            if ($tInfo['max_amount'] > 0 && $balance > $tInfo['max_amount']) {
                ajax_return("余额大于进入游戏的最高限额", 0);
            }
        }

        $other['platform'] = $this->param['platform'] ?? 0;
        $other['back_url'] = $this->param['back_url'] ?? '';
        $data = \app\common\FastGame\Base\Game::getGameUrl($gid, $this->uid, true, $other);
        //增加游玩游戏次数
        UserGames::where([['ug_tcid', '=', $games['g_tcid']], ['ug_gid', '=', $gid], ['ug_uid', '=', $this->uid]])->inc('ug_play_count', 1)->update(
            ['ug_lastupdate' => time()]);
        $datas = json_decode($data->getContent(), true);
        $rsps['code'] = 0;
        $rsps['msg'] = $datas['msg'] ?? '';
        if ($datas['code'] == "00000") {
            //获取lucky port token
            $condition = [];
            $condition[] = ['ut_uid', '=', $this->uid];
            $condition[] = ['ut_tcid', '=', $tcDetail['tc_id']];
            $token = UserThirds::where($condition)->order('ut_intime desc')->value('ut_token');
            $rsps['code'] = 1;
            $rsps['data'] = ['token' => $token, 'url' => $datas['data']['url']];
        }
        return $rsps;
    }

    public function index_game_layout()
    {
        $limit = $this->param['nlimit'] ?? 11;
        if (isset($header['usertoken'])) {
            try {
                $token = TokenCheck::run($header, []);
                $this->uid = $token->uid;
            } catch (Exception $e) {
                throw new \app\common\exception\MessageException(lang($e->getMessage()), $e->getCode());
            }
        }
        // 获取菜单数据
        $menus = (new \app\api\factory_v2\IndexFactory($this->uid))->menu_run()['data']['index_game_layout'];
        //重新组装数据
        $data = [];
        foreach ($menus as $v) {
            $arr = [
                'name' => $v['icon'] ?: $v['name'],
                'icon' => $v['icon_url'],
                'href' => $v['href'],
                'type' => 'game_list',
            ];
            if ($v['extra'] == 'platform') {
                //平台列表
                $arr['list'] = $this->get_game_third()['data'];
                $arr['type'] = 'platform_list';
            } else {
                //游戏列表
                $arr['list'] = $this->getGameListByMark($v['extra'], $limit);
            }
            $data[] = $arr;
        }
        $rsps['code'] = 1;
        $rsps['data'] = $data;
        return $rsps;
    }

    public function get_user_third_balance()
    {
        $tid = $this->param['tid'] ?? 0;
        $data = \app\common\FastGame\Base\Game::getThirdBalance($tid, $this->uid);
        return $data;
    }

    public function user_third_transout()
    {
        $tid = $this->param['tid'] ?? 0;
        $apiRedis = new ApiRedis();
        if (!$apiRedis->lock('third_withdrawal_' . $tid . '_' . $this->uid)) {
            ajax_return('请勿频繁访问');
        }
        if ($tid == 0) {
            $data = \app\common\FastGame\Base\Game::withdrawThirdAll($this->uid);
        } else {
            $data = \app\common\FastGame\Base\Game::withdrawThird($tid, $this->uid);
        }
        if ($data) {
            $info['code'] = 1;
        } else {
            $info['code'] = 0;
        }
        //获取用户余额
        $info['balance'] = UserModel::where(['id' => $this->uid])->value('balance') ?: 0;
        return json($info);
    }

    public function get_game_logs($start = 0, $end = 0, $tc_code = '')
    {
//        $tid = $this->param['tid']??0;
//        \app\common\FastGame\Base\ThirdTask::run();
        //FB
        $ThirdConfigs = app()->make(ThirdConfigs::class);
        $tc_id = $ThirdConfigs->where(['tc_code' => $tc_code])->value('tc_id');
        if (!$tc_id) {
            echo '平台不存在';
            return false;
        }
        if ($tc_code == 'FB') {
            $flags = [
                "start" => $start * 1000,
                "end" => $end * 1000,
            ];
        } else if ($tc_code == 'WM') {
//            $tc_id = $ThirdConfigs->where(['tc_code' => $tc_code])->value('tc_id');
            //只传平台id 不传游戏id
            $flags = [
                "start" => $start,
                "endtime" => $end,
            ];
        } else if ($tc_code == 'aecricket') {
            $flags = [
                "start" => $start,
                "end" => $end,
            ];
        } else if ($tc_code == 'GW') {
            $flags = [
                "start" => $start,
                "endtime" => $end,
            ];
        }
        $apicfgs = Games::getConfigs('', $tc_id);
        return Third::run($apicfgs, 'get_game_logs', $flags); //注单获取的开始时间结束时间等在在otherkvs中指定

    }

    public function get_three_game_list($tc_code)
    {
//        $tid = $this->param['tid']??0;
//        \app\common\FastGame\Base\ThirdTask::run();
        //FB
        $ThirdConfigs = app()->make(ThirdConfigs::class);
        $tc_id = $ThirdConfigs->where(['tc_code' => $tc_code])->value('tc_id');
        if (!$tc_id) {
            echo '平台不存在';
            return false;
        }
        $apicfgs = Games::getConfigs('', $tc_id);
        return Third::run($apicfgs, 'get_game_list', $flags); //注单获取的开始时间结束时间等在在otherkvs中指定

    }


    //获取小游戏url
    public function get_game_url()
    {
        $param = $this->param;
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $keywords = $this->param['keywords'] ?? '';
        $GameSetModel = GameSetModel::field('id,game_sys_sign,game_name,game_status,sort,icon,wallet_type,tc_id')
            ->when(isset($param['t_id']), function ($query) use ($param) {
                $query->where(['type' => $param['t_id']]);
            })
            ->when(!empty($keywords), function ($query) use ($keywords) {
                $query->where([['game_name', 'like', "%" . $keywords . "%"]]);
            })
            ->when((isset($param['tc_id']) && $param['tc_id'] > 0), function ($query) use ($param) {
                $query->where(['tc_id' => $param['tc_id']]);
            })
            ->where([['game_status', '>', 0]])
            ->limit((($page - 1) * $limit), $limit)
            ->order('sort desc')
            ->select();

        if (!isset($GameSetModel[0]['id'])) {
            ajax_return("not game", 0);
        }
        $rsps['code'] = 1;
        $rsps['data'] = $GameSetModel;
        return $rsps;
    }

    /**
     * 重新登录或者是回到首页 回到我方系统时 将每个转账模式游戏的余额取回来
     * @return array
     */
    public function get_platform_balance(): array
    {
        /* @var GameSetModel $GameSetModel */
        $GameSetModel = app()->make(GameSetModel::class);
        $GameSet = $GameSetModel->alias('a')
            ->join('game_user b', 'b.game_id = a.id')
            ->where(['a.game_status' => 1, 'a.wallet_type' => 2, 'b.online' => 1])
            ->where(['b.uid' => $this->uid])
            ->group('game_sys_sign')
            ->field('a.id')
            ->select();
        foreach ($GameSet as $v) {
            $this->transfer_out($v['id']);
        }
        return ['code' => 1];
    }


    /**
     * 注单表的统计数据 暂定每天凌晨 00:30 更新 （使用命令行加计划任务）
     * @param int $gid
     * @param string $day
     * @throws \think\Exception
     */
    public function game_report(int $gid = 0, string $day = 'yesterday')
    {
        if ($gid == 0) {
            $gid = $this->param['gid'] ?? 0;
        }
        /* @var GameReportOperatingModel $GameReportOperatingModel */
        $GameReportOperatingModel = app()->make(GameReportOperatingModel::class);
        /* @var GameSetModel $GameSetModel */
        $GameSetModel = app()->make(GameSetModel::class);
        $game = $GameSetModel->get_game_platform($gid);
        try {
            $tx_arr = Config::get('custom.sports');
            //普通游戏使用注单表  体育游戏使用 tx表
            if (in_array($game['game_sys_sign'], $tx_arr)) {
                $TxModel = $this->getTxTable($day, false);
                $bets_number = $TxModel->where(['game_id' => $gid])->group('uid')->count(); //投注人数
                $bets_total = $TxModel->where(['game_id' => $gid])->sum('bet_amount'); //玩家投注
                $pay_total = $TxModel->where(['game_id' => $gid])->sum('payout'); //平台支出
                $user_total_loss = $pay_total - $bets_total;
                $platform_total_lose = $bets_total - $pay_total;
                $game_name = $game['game_sys_sign'];
                $game_code = $game['game_code'];
                $day_time = date('Y-m-d', strtotime(trim($TxModel->getSuffix(), '_')));
                $add_time = time();
            } else {
                $ZhudanModel = $this->getZhudanTable($day, false);
                $bets_number = $ZhudanModel->where(['game_id' => $gid])->group('username')->count(); //投注人数
                $bets_total = $ZhudanModel->where(['game_id' => $gid])->sum('bets_coin'); //玩家投注
                $pay_total = $ZhudanModel->where(['game_id' => $gid])->sum('reward_coin'); //平台支出
                $user_total_loss = $pay_total - $bets_total;
                $platform_total_lose = $bets_total - $pay_total;
                $game_name = $game['game_sys_sign'];
                $game_code = $game['game_code'];
                $day_time = date('Y-m-d', strtotime(trim($ZhudanModel->getSuffix(), '_')));
                $add_time = time();
            }
            $data = compact('bets_number', 'bets_total', 'pay_total', 'user_total_loss', 'platform_total_lose', 'game_name', 'add_time', 'day_time', 'game_code');
            $model = $GameReportOperatingModel->where(['day_time' => $day_time, 'game_name' => $game_name, 'game_code' => $game_code])->find();
            if ($model) {
                $res = $model->save($data);
            } else {
                $res = $GameReportOperatingModel->create($data);
            }
            if ($res) {
                $rsps['code'] = 1;
            } else {
                $rsps['code'] = 0;
            }
            return $rsps;
        } catch (\Exception $e) {
            $rsps['msg'] = lang('暂无数据');
            $rsps['code'] = 0;
            return $rsps;
//            throw new \think\Exception($e->getMessage(), 0);
        }
    }


    /**
     * 获取游戏平台存在的唯一用户
     * @param Object $model
     * @param Object $game
     * @param int $uid
     * @return array
     * @throws Exception
     */
    function getUserData(Object $model, Object $game, int $uid): array
    {
        try {
            $GameUserModel = new GameUserModel();
            $model = $game_user = $GameUserModel->alias('a')
                ->join('game_set b', 'a.game_id = b.id')
                ->where(["a.uid" => $uid])
                ->where(['b.game_sys_sign' => $game['game_sys_sign']])
                ->field('a.*')
                ->find();
            $toArray = $model ? $model->toArray() : [];
            return [$model, $toArray];
        } catch (\Exception $e) {
            throw new \think\Exception(lang('参数异常'), 0);
        }
    }

    public function game_list()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 100;
        $tc_id = $this->param['tc_id'] ?? 0;
        $t_id = $this->param['t_id'] ?? 0;
        $sports = $this->param['sports'] ?? 0;
        $keywords = $this->param['keywords'] ?? '';
        $tag = $this->param['tag'] ?? '';
        $hot = $this->param['hot'] ?? '';
        $isPlay = $this->param['is_play'] ?? '';
        $walletType = $this->param['wallet_type'] ?? '';
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $rsps['code'] = 1;
        $redis_key = md5("game-list-" . $page . "-" . $limit);
        $lists = $ApiRedis->get($redis_key);
        //if(empty($lists)){
        $where = [];
        $where[] = ["a.g_state", ">", 0];
        $where[] = ['g_is_del', '=', 0];
        $where[] = ['a.g_show', '=', 1];

        if (!empty($sports)) {
            if ($sports == 1) {
                $where[] = ["a.g_sports", "=", 1];
            } else {
                $where[] = ["a.g_sports", "=", 0];
            }
        }

        $uid = 0;
        //如果登录就获取用户uid，不抛出异常（用于判断用户是否收藏此游戏）
        try {
            $header = request()->header();
            $token = TokenCheck::run($header, []);
            $uid = $token->uid;
        } catch (\Exception $e) {
        }

        $wherestr = '';
        if (!empty($lang)) {
            $wherestr = 'c.country not like "%' . $lang . '%" or c.country is null';
        }

        if (!empty($tc_id)) {
            $where[] = ["a.g_web_tcid", "=", $tc_id];
        }

        //钱包类型
        if ($walletType !== '') {
            $where[] = ['a.g_cj', 'in', $walletType];
        }

        //t_id为collect_id时为收藏
        if (!empty($t_id) && $t_id == 'collect_id' && $uid > 0) {
            $collectGidSql = Db::name('user_collect')
                ->where('type', '=', UserCollectModel::GAME_TYPE)
                ->where('user_id', $uid)->field('other_id')->buildSql();
            $where[] = ["a.g_id", "exp", Db::raw('IN' . $collectGidSql)];
        } else if (!empty($t_id)) {
            $where[] = ["a.g_type", "=", $t_id];
        }
        $tag_where = [];
        if (!empty($keywords)) {
            $tag_where[] = ["i.gi_name|a.g_keyword", "like", "%" . $keywords . "%"];
        }
//        if(!empty($tag)){
//            if (strtolower($tag) == 'top') {
//                $tag_where[] = ["a.g_is_optimum", "=", 1];
//            } elseif (strtolower($tag) == 'hot') {
//                $tag_where[] = ["a.g_hot", "=", 1];
//            } elseif (strtolower($tag) == 'new') {
//                $tag_where[] = ["a.g_is_new", "=", 1];
//            } else {
//                $tag_where[] = ["a.g_keyword", "=", $tag];
//            }
//        }
        //是否推荐（热门）
        if ($hot !== '') {
            $where[] = ['g_hot', '=', $hot];
        }

        /* @var GamesModel $GamesModel */
        $GamesModel = app()->make(GamesModel::class);
        $query = $GamesModel
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_web_tcid = c.tc_id');

        $field = 'a.g_id as id,a.g_icon as icon,a.g_type,a.g_state as game_status,a.g_web_tcid as g_tcid,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content, c.tc_code,c.tc_name,a.g_is_new,a.g_is_optimum,a.g_is_self,a.g_exfield,a.g_hot,c.is_new_tab,a.g_cj as wallet_type,a.g_web_tcid';
        $order = 'a.g_sort desc, a.g_id desc';

        //判断是否常玩
        if ($isPlay == 1 && $uid) {
            $gids = UserGames::where('ug_uid', '=', $uid)
                ->group('ug_gid')->column('ug_gid');
            $where[] = ['a.g_id', 'in', $gids];
            $query = $query->join('user_games ug', 'ug.ug_gid=a.g_id')
                ->group('a.g_id');
            $field .= ',ug.ug_play_count as play_count';
            $order = 'play_count desc,' . $order;
        }

        $lists = $query
            ->where($where)
            ->where($tag_where)
            ->where($wherestr)
            ->limit((($page - 1) * $limit), $limit)
            ->field($field)
            ->order($order)
            ->filter(function ($data) use ($sys, $uid) {
                if ($data['icon'] == '/static/admin/img/add.png') {
                    $data['icon'] = $sys['game_default_pic'];
                }
                if ($data['gi_headimg'] == '/static/admin/img/add.png') {
                    $data['gi_headimg'] = $sys['game_default_pic'];
                }
                //判断是否收藏
                $data['is_collect'] = CollectFactory::judgeIsCollect(UserCollectModel::GAME_TYPE, $uid, $data['id']);
                $data['lottery'] = GameService::getLotteryInfo($data['id']);
            })
            ->select();

        //$ApiRedis->set($redis_key, $lists, 60);
        //}

        $rsps['data'] = $lists;
        $rsps['tags'] = self::getTags($where);
        return $rsps;
    }

    private static function getTags($where = null)
    {
        if (is_null($where)) {
            $where = [];
            $where[] = ["a.g_state", ">", 0];
        }
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where_country = '';
        if (!empty($lang)) {
            $where_country = 'c.country not like "%' . $lang . '%" or c.country is null';
        }
        $GamesModel = app()->make(GamesModel::class);
        $is_new = $GamesModel->alias('a')->join('third_configs c', 'a.g_tcid = c.tc_id')->where($where)->where($where_country)->where('a.g_is_new', 1)->count();
        $is_hot = $GamesModel->alias('a')->join('third_configs c', 'a.g_tcid = c.tc_id')->where($where)->where($where_country)->where('a.g_hot', 1)->count();
        $is_top = $GamesModel->alias('a')->join('third_configs c', 'a.g_tcid = c.tc_id')->where($where)->where($where_country)->where('a.g_is_optimum', 1)->count();
        $tags_list = $GamesModel->alias('a')->join('third_configs c', 'a.g_tcid = c.tc_id')->where($where)->where($where_country)->where('a.g_keyword', '<>', '')->distinct(true)->limit(10)->column('g_keyword');
        $tags = [];
        if ($is_top) {
            $tags[] = lang('top');
        }
        if ($is_hot) {
            $tags[] = lang('hot');
        }
        if ($is_new) {
            $tags[] = lang('new');
        }
        return array_filter(array_unique(array_merge($tags, array_unique($tags_list))));
    }

    public function game_detail()
    {
        $g_id = $this->param['g_id'] ?? 0;
        $where = [];
        $where[] = ["a.g_id", "=", $g_id];
        $where[] = ['g_is_del', '=', 0];
        $where[] = ['g_state', '>', 0];
        $detail = Db::name('games')->alias('a')->join('game_infos i', 'a.g_id = i.gi_gid')
            ->where($where)
            ->field('a.g_id,a.g_tag,a.g_icon,a.g_state,i.gi_name,i.gi_info,i.gi_headimg,i.gi_content')
            ->find();
        if (empty($detail)) {
            ajax_return('游戏不存在');
        }
        //判断是否收藏
        $detail['is_collect'] = CollectFactory::judgeIsCollect(UserCollectModel::GAME_TYPE, $this->uid, $detail['g_id']);
        $detail['lottery'] = GameService::getLotteryInfo($detail['g_id']);
        $rsps = [];
        $rsps['code'] = 1;
        $rsps['data'] = $detail;
        return $rsps;
    }

    /*
    * 游戏订单
    * @param  page 页码
    * @param  limit 数量
    * @param  g_id 游戏ID
    * @param  status 输赢状态 0无效 1未结算 2输 3赢 4平
    * @param  isday 1 当天 0 生效date
    * @param  settlement 结算 0 所有 1 已结算 2 未结算
    * @param  sports 类型 0 所有 1 体育 2 其他三方游戏
    * @param  date 格式 2021-08-01|2021-08-05，2021-08-01当做2021-08-01|2021-08-01处理
    * return array
    */
    public function game_order()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 100;
        $g_id = $this->param['g_id'] ?? 0;
        $status = $this->param['status'] ?? -1;
        $isday = $this->param['isday'] ?? 0;
        $sports = $this->param['sports'] ?? 0;
        $settlement = $this->param['settlement'] ?? 0;
        $date = $this->param['date'] ?? "";
        $rsps['code'] = 1;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $where = [];
        $where[] = ["gb_uid", "=", $this->uid];
        if ($status >= 0) {
            $where[] = ["gb_winprice", "=", $status];
        }
        if ($g_id > 0) {
            $where[] = ["gb_gid", "=", $g_id];
        }

        if ($settlement == 1) {
            $where[] = ["gb_winlose", ">", 1];
        }
        if ($settlement == 2) {
            $where[] = ["gb_winlose", "<", 2];
        }
        $whereG = "";
        if ($sports == 1) {
            $sql = Db::name('games')->where([["g_sports", "=", 1]])->field("g_id")->buildSql();
            $whereG = "gb_gid in(" . $sql . ")";
        } elseif ($sports == 2) {
            $sql = Db::name('games')->where([["g_sports", "<>", 1]])->field("g_id")->buildSql();
            $whereG = "gb_gid in(" . $sql . ")";
        }
        $game = Db::name('game_infos')->column("gi_name", "gi_gid");
        $start_time = $end_time = 0;
        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $start_time = $time[0];
                $end_time = $time[1];
            }
        }

        if ($start_time == 0 || $end_time == 0) {
            $start_time = time() - 86400;
            $end_time = time();
        }
        $betWhere = BetService::getGbidByTime($start_time, $end_time)['sql'];
        $lists = Db::name('game_bets')->where($where)->where($whereG)->where($betWhere)
            ->limit((($page - 1) * $limit), $limit)
            ->field('gb_id,gb_gid,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_validwater')
            ->order("gb_id desc")
            ->select();

        if (!empty($lists)) {
            $lists = $lists->toArray();
            foreach ($lists as $key => &$value) {
                $value['gb_price'] = Util::i18n_currency($value['gb_price']);
                $value['gb_winprice'] = Util::i18n_currency($value['gb_winprice']);
                $value['gb_bettime'] = Util::i18n_datetime2str($value['gb_bettime']);
                $value['gb_overtime'] = Util::i18n_datetime2str($value['gb_overtime']);
                $value['gb_validwater'] = Util::i18n_currency($value['gb_validwater']);
                $value['game_name'] = isset($game[$value['gb_gid']]) ? $game[$value['gb_gid']] : '';
                $icon = GamesModel::where(['g_id' => $value['gb_gid']])->value('g_icon');
                $value['game_icon'] = $icon == '/static/admin/img/add.png' ? $sys['game_default_pic'] : $icon;
            }
        }
        $total = Db::name('game_bets')->where($where)->where($whereG)->where($betWhere)->count();


        $rsps['total'] = $total;
        $rsps['data'] = $lists;
        return $rsps;
    }

    /*
    * 游戏订单（旧）
    * @param  page 页码
    * @param  limit 数量
    * @param  g_id 游戏ID
    * @param  status 输赢状态 0无效 1未结算 2输 3赢 4平
    * @param  isday 1 当天 0 生效date
    * @param  settlement 结算 0 所有 1 已结算 2 未结算
    * @param  date 格式 2021-08-01|2021-08-05，2021-08-01当做2021-08-01|2021-08-01处理
    * return array
    */
    public function game_order1()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $game_id = $this->param['game_id'] ?? 0;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        $rsps['code'] = 1;

        $where = "1=1";
        $where .= " and username='BG_USD_" . $this->uid . "'";
        if ($game_id > 0) {
            $where .= " and game_id=" . intval($game_id);
        }

        $start_time = $end_time = 0;
        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $start_time = $time[0];
                $end_time = $time[1];
            }
        }

        if ($start_time == 0 || $end_time == 0) {
            $start_time = time() - 7 * 86400;
            $end_time = time();
        }
        $table = "dq_game_zhudan_";
        $list = $sql = $tables = [];
        for ($i = $end_time; $i >= $start_time; $i -= 86400) {
            if (existTable($table . date("Ymd", $i))) {
                $tables[] = $table . date("Ymd", $i);
            }
        }

        if (!empty($tables)) {
            foreach ($tables as $key => $val) {
                if ($key > 0) {
                    $sql[] = "select zd_id,username,bets_coin,reward_coin,game_name,add_time from {$val} where {$where} order by `add_time` desc";
                }
            }
            if (empty($sql)) {
                $list = Db::connect()->table($tables[0])
                    ->where($where)
                    ->limit((($page - 1) * $limit), $limit)
                    ->field('zd_id,username,bets_coin,reward_coin,game_name,add_time')
                    ->order("add_time desc")
                    ->select();
            } else {
                $list = Db::connect()->table($tables[0])
                    ->unionAll($sql)
                    ->where($where)
                    ->limit((($page - 1) * $limit), $limit)
                    ->field('zd_id,username,bets_coin,reward_coin,game_name,add_time')
                    ->order("add_time desc")
                    ->select();
            }
            if (!empty($list)) {
                $list = $list->toArray();
                foreach ($list as $key => &$value) {
                    $value['add_time'] = Util::i18n_datetime2str($value['add_time']);
                }
            }
        }
        $rsps['data'] = $list;
        return $rsps;
    }

    public function game_order3($params, $isDatabase = false)
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $rsps['code'] = 1;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $start_time = strtotime(date("Y-m-d") . " 00:00:00");
        $end_time = strtotime(date("Y-m-d") . " 23:59:59");
        $game = Db::name('game_infos')->column("gi_name", "gi_gid");
        $betWhere = BetService::getGbidByTime($start_time, $end_time)['sql'];
        $redisKey = 'gameorderCache4:' . md5(json_encode(request()->param()));
        if ($ApiRedis->has($redisKey) && !$isDatabase) {
            $list = json_decode($ApiRedis->get($redisKey), true);
        } else {
            $list = Db::name('game_bets')->where($betWhere)
                ->limit((($page - 1) * $limit), $limit)
                ->field('gb_id,gb_gid,gb_price,gb_uid,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice')
                ->order("gb_id desc")
                ->select();

            if (!empty($list)) {
                $list = $list->toArray();
                foreach ($list as $key => &$value) {
                    $value['gb_time'] = date('H:i:s A', $value['gb_bettime']);
                    $value['gb_bettime'] = Util::i18n_datetime2str($value['gb_bettime']);
                    $value['gb_overtime'] = Util::i18n_datetime2str($value['gb_overtime']);
                    $value['game_name'] = isset($game[$value['gb_gid']]) ? $game[$value['gb_gid']] : '';
                    $icon = GamesModel::where(['g_id' => $value['gb_gid']])->value('g_icon');
                    $value['game_icon'] = $icon == '/static/admin/img/add.png' ? $sys['game_default_pic'] : $icon;
                    if ($value['gb_winprice'] == 0) {
                        $value['gb_winprice'] = $value['gb_price'];
                    }
                    $value['gb_price'] = Util::i18n_currency($value['gb_price']);
                    $value['gb_winprice'] = Util::i18n_currency($value['gb_winprice']);
                    //获取用户名称和头像
                    $userDetail = UserModel::where('id', '=', $value['gb_uid'])->field('username,mail,phone,head_img')->find();
                    $value['username'] = '';
                    $value['avatar'] = DefaultHeadModel::getHeadOne();//默认头像
                    if ($userDetail) {
//                        $value['username'] = MemberService::getNickname($value['gb_uid'], $userDetail, true);
                        $value['username'] = hideStr($value['gb_uid'], 2);#显示UID
                        $value['avatar'] = $userDetail['head_img'] ?: $value['avatar'];
                    }

                }
            }

            $list1 = [];
            if (count($list) < $limit) {
                $list1 = $this->generateVirtualData($limit - count($list));
            }
            $list = array_merge($list1, $list);
            $ApiRedis->set($redisKey, json_encode($list), 60 * 5);
        }

        $rsps['data'] = $list;
        return $rsps;
    }

    /**
     * @param $num 生成数据条数
     * @param $type 数据类型 1: 投注， 2 最高赢钱， 3 最高下注
     * @param int $min_amount
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function generateVirtualData($num = 10, $type = 1, int $min_amount = 1)
    {
        $ApiRedis = new ApiRedis();
        $redis_key = md5("generateVirtualData-v3{$type}:" . $num);
        $redis_type_key = md5("generateVirtualData{$type}");
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data = [];
        }
        $BangdanModel = new BangdanModel();
        $name_temp_json = $BangdanModel->where('start_date', '<', time())->where('end_date', '>', time())->value('f_a_arr');
        if (empty($name_temp_json)) {
            $name_temp_list = [];
            for ($i = 1; $i <= 20; $i++) {
                $name_temp_list[] = [
                    'name' => rand(100, 999) . "***" . rand(100, 999)
                ];
            }
        } else {
            $name_temp_list = json_decode($name_temp_json, true);
        }
        if (empty($data) || !$ApiRedis->has($redis_type_key)) {
            $game = Db::name('fake_game_set')->where('max_amount', '>', $min_amount)->where('status', 1)->select();
            if ($game->isEmpty()) {
                return [];
            }
            $time = time();
            for ($i = 0; $i < $num; $i++) {
                $rand_time = random_int($i, $num);
                $rand_game = $game->shuffle()->first();
                $username = isset($name_temp_list[$i]) ? $name_temp_list[$i]['name'] : rand(100, 999) . "***" . rand(100, 999);
                $row = [];
                $row['gb_gid'] = $rand_game['gid'];
                $row['gb_tcid'] = $rand_game['tcid'];
                $row['gb_sn'] = get_rand_char(20);
                $row['username'] = $username;
                $row['gb_price'] = round(random_int(max([$rand_game['min_amount'], $min_amount]), $rand_game['max_amount']), 2);
                $row['gb_winprice'] = round($row['gb_price'] * (random_int($rand_game['min_rate'], $rand_game['max_rate']) / 100), 2);
                $row['gb_winlose'] = 3;
                $row['gb_betdate'] = Util::i18n_date2str($time - $rand_time);
                $row['gb_bettime'] = Util::i18n_datetime2str($time - $rand_time);
                $row['gb_time'] = date('H:i:s A', $time - $rand_time);
                $row['game_name'] = $rand_game['game_name'];
                $row['game_icon'] = $rand_game['game_icon'];
                $data[] = $row;
            }
            if ($type != 1) {
                $last_names = array_column($data, $type == 2 ? 'gb_winprice' : 'gb_price');
                array_multisort($last_names, SORT_DESC, $data);//最低 降序
                $data = array_slice($data, 0, $num);
            }
            $ApiRedis->set($redis_key, $data, $type == 1 ? 30 : 86400);
            $ApiRedis->set($redis_type_key, 1, $type == 1 ? 30 : 3600);
        }
        return $data;
    }


    public function game_order4($params = [], $isDatabase = false)
    {
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $redis_key = "game_order4" . $limit;
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if ($ApiRedis->has($redis_key) && !$isDatabase) {
            return $ApiRedis->get($redis_key);
        }
        $game = Db::name('game_infos')->column("gi_name", "gi_gid");
        $start_time = strtotime(date("Y-m-d") . " 00:00:00");
        $end_time = strtotime(date("Y-m-d") . " 23:59:59");
        $betWhere = BetService::getGbidByTime($start_time, $end_time)['sql'];
        // 最高盈利金额订单
        $min_game_win_price = env('min_game_win_price', 500);
        $high_win_list = Db::name('game_bets')->alias('gb')->where($betWhere)
            ->join('third_configs tc', 'tc.tc_id = gb.gb_tcid')
            ->where('gb_winprice', '>', $min_game_win_price)
            ->limit($limit)
            ->field('gb_id,gb_gid,gb_tcid,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,is_new_tab')
            ->order("gb_winprice desc")
            ->select();

        if (!empty($high_win_list)) {
            $high_win_list = $high_win_list->toArray();
            foreach ($high_win_list as $key => &$value) {
                $value['gb_time'] = date('H:i:s A', $value['gb_bettime']);
                $value['gb_betdate'] = Util::i18n_date2str($value['gb_bettime']);
                $value['gb_bettime'] = Util::i18n_datetime2str($value['gb_bettime']);
                $value['gb_overtime'] = Util::i18n_datetime2str($value['gb_overtime']);
                $value['game_name'] = isset($game[$value['gb_gid']]) ? $game[$value['gb_gid']] : '';
                $icon = GamesModel::where(['g_id' => $value['gb_gid']])->value('g_icon');
                $value['game_icon'] = $icon == '/static/admin/img/add.png' ? $sys['game_default_pic'] : $icon;
                $value['username'] = rand(100, 999) . "***" . rand(100, 999);
            }
        }

        $high_win_list2 = [];
        if (count($high_win_list) < $limit) {
            $high_win_list2 = $this->generateVirtualData($limit - count($high_win_list), 2, $min_game_win_price);
        }

        // 最高投注金额订单
        $min_game_bet_price = env('min_game_bet_price', 500);
        $high_bet_list = Db::name('game_bets')->alias('gb')->where($betWhere)
            ->join('third_configs tc', 'tc.tc_id = gb.gb_tcid')
            ->where('gb_price', '>', $min_game_bet_price)
            ->limit($limit)
            ->field('gb_id,gb_gid,gb_tcid,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,is_new_tab')
            ->order("gb_price desc")
            ->select();

        if (!empty($high_bet_list)) {
            $high_bet_list = $high_bet_list->toArray();
            foreach ($high_bet_list as $key => &$value) {
                $value['gb_time'] = date('H:i:s A', $value['gb_bettime']);
                $value['gb_betdate'] = Util::i18n_date2str($value['gb_bettime']);
                $value['gb_bettime'] = Util::i18n_datetime2str($value['gb_bettime']);
                $value['gb_overtime'] = Util::i18n_datetime2str($value['gb_overtime']);
                $value['game_name'] = isset($game[$value['gb_gid']]) ? $game[$value['gb_gid']] : '';
                $icon = GamesModel::where(['g_id' => $value['gb_gid']])->value('g_icon');
                $value['game_icon'] = $icon == '/static/admin/img/add.png' ? $sys['game_default_pic'] : $icon;
                $value['username'] = rand(100, 999) . "***" . rand(100, 999);

            }
        }

        $high_bet_list2 = [];
        if (count($high_bet_list) < $limit) {
            $high_bet_list2 = $this->generateVirtualData($limit - count($high_bet_list), 3, $min_game_bet_price);
        }
        $ret = [];
        $ret['code'] = 1;
        $ret['data'] = [
            'high_bet' => array_merge($high_bet_list, $high_bet_list2),
            'high_win' => array_merge($high_win_list, $high_win_list2),
        ];
        $ApiRedis->set($redis_key, $ret, 60 * 5);
        return $ret;
    }

    /*
    * 体育订单（旧）
    * @param  page 页码
    * @param  limit 数量
    * @param  g_id 游戏ID
    * @param  status 输赢状态 0无效 1未结算 2输 3赢 4平
    * @param  isday 1 当天 0 生效date
    * @param  settlement 结算 0 所有 1 已结算 2 未结算
    * @param  date 格式 2021-08-01|2021-08-05，2021-08-01当做2021-08-01|2021-08-01处理
    * return array
    */
    public function game_order2()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $game_id = $this->param['game_id'] ?? 0;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        $rsps['code'] = 1;
        $where = "1=1";
        $where .= " and uid=" . $this->uid;
        if ($game_id > 0) {
            $where .= " and game_id=" . intval($game_id);
        }

        $game = GameSetModel::where('game_status=1')->column('game_name', 'id');

        $start_time = $end_time = 0;
        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $start_time = $time[0];
                $end_time = $time[1];
            }
        }

        if ($start_time == 0 || $end_time == 0) {
            $start_time = time() - 7 * 86400;
            $end_time = time();
        }
        $table = "dq_game_tx_";
        $list = $sql = $tables = [];
        for ($i = $end_time; $i >= $start_time; $i -= 86400) {
            if (existTable($table . date("Ymd", $i))) {
                $tables[] = $table . date("Ymd", $i);
            }
        }
        if (!empty($tables)) {
            foreach ($tables as $key => $val) {
                if ($key > 0) {
                    $sql[] = "select tx_number,game_id,bet_amount,payout,bet_status,status,add_time from {$val} where {$where} order by `add_time` desc";
                }
            }

            if (empty($sql)) {
                $list = Db::connect()->table($tables[0])
                    ->where($where)
                    ->limit((($page - 1) * $limit), $limit)
                    ->field('tx_number,game_id,bet_amount,payout,bet_status,status,add_time')
                    ->order("add_time desc")
                    ->select();
            } else {
                $list = Db::connect()->table($tables[0])
                    ->unionAll($sql)
                    ->where($where)
                    ->limit((($page - 1) * $limit), $limit)
                    ->field('tx_number,game_id,bet_amount,payout,bet_status,status,add_time')
                    ->order("add_time desc")
                    ->select();
            }
            if (!empty($list)) {
                $list = $list->toArray();
                foreach ($list as $key => &$value) {
                    $value['add_time'] = Util::i18n_datetime2str($value['add_time']);
                    $value['game_name'] = $game[$value['game_id']];
                    if (empty($value['status'])) {
                        $value['status'] = lang('未结算');
                    }
                }
            }
        }
        $rsps['data'] = $list;
        return $rsps;
    }

    public function game_order_detail()
    {
        $gb_id = $this->param['gb_id'] ?? 0;
        $where = [];
        $where[] = ["gb_id", "=", $gb_id];
        $where[] = ["gb_uid", "=", $this->uid];
        $rsps['code'] = 1;
        $lists = Db::connect('game')->table('game_bets')
            ->where($where)
            ->field('gb_id,gb_gid,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice')
            ->find();
        $rsps['data'] = $lists;
        return $rsps;
    }


    public function game_statistics()
    {
        $ApiRedis = new ApiRedis();
        $redis_key = md5('game_statistics_' . $this->uid);
        $lists = $ApiRedis->get($redis_key);
        if ($lists) {
            $list = $ApiRedis->get($redis_key);
        } else {
            //转化成时区表
            $table = \app\common\service\BetService::getThirdStatsTable();
            $list = [
//                'total_price'=> sprintf("%.2f",Db::name('third_stats_users')->where(['su_uid'=>$this->uid])->sum('su_bet')), //总赌注
//                'total_price'=> sprintf("%.2f",Db::name('third_stats_users')->where(['su_uid'=>$this->uid])->sum('su_validwater')), //总赌注
                'total_price' => Util::i18n_currency(Db::name($table["_stats_global"])->where([['su_uid', '=', $this->uid]])->sum('su_valid_bet')),//及时有效流水
                'total_winprice' => Util::i18n_currency(Db::name($table["_stats_users"])->where(['su_uid' => $this->uid])->sum('su_win')), //总盈利
                'total_loss' => Util::i18n_currency(Db::name($table["_stats_users"])->where(['su_uid' => $this->uid])->sum('su_lose')), //总亏损
            ];
            $ApiRedis->set($redis_key, $list, 60);
        }
        return ['code' => 1, 'data' => $list];
    }

    public function agent_report()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $user = $this->param['user'] ?? "";
        $start_time = $this->param['start_time'] ?? '';
        $end_time = $this->param['end_time'] ?? '';
        $where = [];

        if (!empty($user)) {
            $uids = UserModel::where([['id|username', '=', $user]])->column("id");
            $where[] = ["sff_uid", "in", $uids];
        } else {
            $where[] = ['sff_uid', '=', $this->uid];
        }

        if (!empty($start_time)) {
            $where[] = ["sff_statstime", ">=", strtotime($start_time . " 00:00:00")];
        }

        if (!empty($end_time)) {
            $where[] = ["sff_statstime", "<=", strtotime($end_time . " 23:59:59")];
        }

        $lists = Db::name('third_stats_fyfy')->where($where)
            ->limit((($page - 1) * $limit), $limit)
            ->field('*,' . '(select username from dq_user where id=sff_uid limit 1) as username')
            ->order("sff_id desc")
            ->select();

        $list = [];
        foreach ($lists as &$v) {
            $v['sff_statstime'] = date('Y-m-d H:i:s', $v['sff_statstime']);
            $list[] = $v;
        }
        $total = Db::name('third_stats_fyfy')->where($where)->count();
        return ['code' => 1, 'total' => $total, 'data' => $list];
    }

    public function back_report()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $user = $this->param['user'] ?? "";
        $start_time = $this->param['start_time'] ?? '';
        $end_time = $this->param['end_time'] ?? '';
        $where = [];

        if (!empty($user)) {
            $uids = UserModel::where([['id|username', '=', $user]])->column("id");
            $where[] = ["sb_uid", "in", $uids];
        } else {
            $where[] = ['sb_uid', '=', $this->uid];
        }

        if (!empty($start_time)) {
            $where[] = ["sb_dt", ">=", date("Ymd", strtotime($start_time))];
        }

        if (!empty($end_time)) {
            $where[] = ["sb_dt", "<=", date("Ymd", strtotime($end_time))];
        }

        $lists = Db::name('third_stats_back')->where($where)
            ->limit((($page - 1) * $limit), $limit)
            ->field('*,' . '(select username from dq_user where id=sb_uid limit 1) as username')
            ->order("sb_id desc")
            ->select();

        $list = [];
        foreach ($lists as &$v) {
            $v['sb_paytime'] = date('Y-m-d H:i:s', $v['sb_paytime']);
            $list[] = $v;
        }
        $total = Db::name('third_stats_back')->where($where)->count();
        $total_price = Db::name('third_stats_back')->where(['sb_uid' => $this->uid, 'sb_payed' => 2])->sum('sb_backmoney');
        return ['code' => 1, 'total' => $total, 'total_price' => $total_price, 'data' => $list];
    }

    //分类会员返水配置
    public function water_configuration()
    {
        /* @var GameTypeModel $GameTypeModel */
        $ApiRedis = new ApiRedis();
        $GameTypeModel = app()->make(GameTypeModel::class);
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $wherestr = '';
        if (!empty($lang)) {
            $wherestr = 'c.country not like "%' . $lang . '%" or c.country is null';
        }
        $data = $ApiRedis->get('water_configuration:' . $lang);
        if (!$data) {
            $data = $GameTypeModel->alias('a')->where(['a.state' => 1])
                ->join('games i', 'a.id=i.g_type and i.g_state=1 and i.g_show=1')
                ->join('third_configs c', 'i.g_tcid = c.tc_id')
                ->where($wherestr)
                ->order('a.sort desc, a.id desc')
                ->field('a.id,a.name')
                ->group('a.id')
                ->select();
            foreach ($data as &$v) {
                $v['vipwater'] = GameBackConfigModel::where(['gbc_gtid' => $v['id']])->field('gbc_viplvl,gbc_rate,' . '(select level_name from dq_user_level where id=gbc_viplvl limit 1) as level_name,' . '(select image from dq_user_level where id=gbc_viplvl limit 1) as image')->select();
                foreach ($v['vipwater'] as &$value) {
                    $value['gbc_rate'] = $value['gbc_rate'] * 100;
                }
            }
            $ApiRedis->set('water_configuration:' . $lang, $data, 180);
        }
        $rsps['code'] = 1;
        $rsps['data'] = $data;
        return $rsps;
    }

    //获取平台和对应三方游戏余额
    public function game_third_balance_run()
    {
        $thirdList = ThirdConfigs::where('tc_status', '=', 1)
            ->field('tc_id,tc_name,icon,simple_icon')
            ->order('sort desc, tc_id desc')
            ->select()->toArray();
        //获取相同平台的id，该id的平台用户是公用的，不能分开显示，合并显示
        $samePlatformIds = env('trans_same_platform_ids', '');
        $samePlatformIdArr = [];
        if ($samePlatformIds) {
            $samePlatformIdArr = explode(',', $samePlatformIds);
        }
        $samePlatformKey = '';
        foreach ($thirdList as $k => &$v) {
            if (in_array($v['tc_id'], $samePlatformIdArr)) {
                if ($samePlatformKey) {
                    $thirdList[$samePlatformKey]['tc_name'] .= '/' . lang($v['tc_name']);
                    unset($thirdList[$k]);
                } else {
                    $samePlatformKey = $k;
                    $v['tc_name'] = lang($v['tc_name']);
                    try {
                        $v['balance'] = Game::getThirdBalance($v['tc_id'], $this->uid) ?: 0;
                    } catch (\Exception $e) {
                        $v['balance'] = 0;
                    }
                }
            } else {
                $v['tc_name'] = lang($v['tc_name']);
                try {
                    $v['balance'] = Game::getThirdBalance($v['tc_id'], $this->uid) ?: 0;
                } catch (\Exception $e) {
                    $v['balance'] = 0;
                }
            }
        }
        $rsps['code'] = 1;
        $rsps['data'] = array_values($thirdList);
        return $rsps;
    }

    //转入游戏余额
    public function game_third_balance_in_run()
    {
        $tcid = $this->param['id'] ?? 0;
        $amount = $this->param['amount'] ?? 0;
        if (!$tcid || !$amount) {
            ajax_return('缺少重要参数');
        }
        //判断用户是否进入过游戏
        if (!UserThirds::where(['ut_uid' => $this->uid, 'ut_tcid' => $tcid])->find()) {
            ajax_return('必须先先登录游戏后才能转入');
        }
        $res = Game::depositThird($tcid, $this->uid, $amount);
        if (!$res['transamount']) {
            ajax_return('转入金额错误');
        }
        $rsps['code'] = 1;
        $rsps['data'] = $res;
        return $rsps;
    }

    //游戏余额日志
    public function game_third_balance_log_run()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $list = Db::name('user_balance_translog')->alias('ubtl')
            ->join('third_configs tc', 'tc.tc_id=ubtl.ubtl_tcid')
            ->where(['ubtl_uid' => $this->uid])
            ->field('tc.tc_name as name,ubtl.ubtl_amount as amount,ubtl.ubtl_status as status,ubtl.ubtl_type as type,ubtl.ubtl_intime as time')
            ->order('ubtl.ubtl_intime desc')
            ->page($page, $limit)
            ->select()->toArray();
        $total = Db::name('user_balance_translog')->alias('ubtl')
            ->join('third_configs tc', 'tc.tc_id=ubtl.ubtl_tcid')
            ->where(['ubtl_uid' => $this->uid])
            ->count();
        foreach ($list as &$v) {
            $v['time'] = date('Y-m-d H:i:s', $v['time']);
        }
        $rsps['code'] = 1;
        $rsps['data'] = $list;
        $rsps['total'] = $total;
        return $rsps;
    }

    //获取试玩游戏链接
    public function try_game_run()
    {
        $game_id = $this->param['game_id'] ?? 0;
        if (!$game_id) {
            ajax_return('Missing important parameters');
        }
        $platform = $this->param['platform'] ?? 0;
        $url = GameService::getGameTryUrl($game_id, $platform);
        if (!$url) {
            ajax_return('This game is not available for trial play');
        }
        $rsps['code'] = 1;
        $rsps['data'] = ['game_url' => $url];
        return $rsps;
    }

}