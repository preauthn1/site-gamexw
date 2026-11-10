<?php

namespace app\api\factory_v2;

use app\api\factory\CollectFactory;
use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\model\UserGames;
use app\common\model\{GameRankModel, GamesModel, GameTypeModel, UserCollectModel};
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\facade\Request;

class GameFactory
{
    private $param;//请求参数

    public function __construct()
    {
        $this->param = Request::param();
    }

    //三方游戏首页
    public function index_run()
    {
        $limit = $this->param['nlimit'] ?? 11;
        $return = [];
        $return['code'] = 1;
        $map = [
            'status' => 1
        ];
        $game_rank = GameRankModel::where($map)->order('sort DESC')->select();
        $data = [];
        if (!empty($game_rank)) {
            foreach ($game_rank as $value) {
                $game_data = $this->getGameListByMark($value['type'], $limit);
                $data[$value['id'] . "_index"] = [
                    'tag' => $value['type'],
                    'name' => $value['name'],
                    'icon' => $value['icon'],
                    'icon_url' => $value['icon_url'],
                    'game' => $game_data['list'],
                    'total' => $game_data['total']
                ];
            }
        }
        $return['data'] = $data;
        return $return;
    }

    /**
     * 三方游戏首页
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function list_index_run()
    {
        $limit = $this->param['nlimit'] ?? 11;
        $return = [];
        $return['code'] = 1;
        $data = [];

        $game_type = (new \app\api\factory\GameFactory())->get_game_type();
        foreach ($game_type['data'] as $value) {
            $game_data = $this->getGameListByType($value['id'], $limit);
            $data[] = [
                'type' => $value['id'],
                'name' => $value['name'],
                'icon' => $value['icon'],
//                'icon_url' => $value['icon_url'],
                'game' => $game_data['list'],
                'total' => $game_data['total']
            ];
        }

        $return['data'] = $data;
        return $return;
    }

    //游戏列表
    public function game_list()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 100;
        $tc_id = $this->param['tc_id'] ?? 0;
        $t_id = $this->param['t_id'] ?? 0;
        $sports = $this->param['sports'] ?? 0;
        $keywords = $this->param['keywords'] ?? '';
        $tag = $this->param['tag'] ?? '';
        $isPlay = $this->param['is_play'] ?? '';
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
            if (strpos($tc_id, ',') >= 0) {
                $tc_id_arr = explode(',', $tc_id);
                $where[] = ["a.g_web_tcid", "in", $tc_id_arr];
            } else {
                $where[] = ["a.g_web_tcid", "=", $tc_id];
            }
        }

        //t_id为collect_id时为收藏
        if (!empty($t_id) && $t_id == 'collect_id' && $uid > 0) {
            $collectGidSql = Db::name('user_collect')
                ->where('type', '=', UserCollectModel::GAME_TYPE)
                ->where('user_id', $uid)->field('other_id')->buildSql();
            $where[] = ["a.g_id", "exp", Db::raw('IN' . $collectGidSql)];
        } else if (!empty($t_id)) {
            if ($t_id == 'hot' || $t_id == 'new') {
                $tag = 1;
                if ($t_id == 'new') {
                    $tag = 3;
                }
            } else {
                if (strpos($t_id, ',') >= 0) {
                    $t_id_arr = explode(',', $t_id);
                    $where[] = ["a.g_type", "in", $t_id_arr];
                } else {
                    $where[] = ["a.g_type", "=", $t_id];
                }
            }

        }
        $tag_where = [];
        if (!empty($keywords)) {
            $tag_where[] = ["i.gi_name|a.g_keyword", "like", "%" . $keywords . "%"];
        }
        //根据分类显示全部类型
        if (!empty($tag)) {
            if ($tag == 1) {
                $tag_where[] = ['a.g_hot', '=', 1];
            } elseif ($tag == 2) {
                $tag_where[] = ['a.g_is_optimum', '=', 1];
            } elseif ($tag == 3) {
                $tag_where[] = ['a.g_is_new', '=', 1];
            } else {
                $tag_where[] = ['a.g_is_self', '=', 1];
            }
        }
        /* @var GamesModel $GamesModel */
        $GamesModel = app()->make(GamesModel::class);
        $query = $GamesModel
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_tcid = c.tc_id');

        $field = 'a.g_id as id,a.g_icon as icon,a.g_type,a.g_state as game_status,a.g_web_tcid as g_tcid,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content, c.tc_code,c.tc_name,a.g_is_new,a.g_is_optimum,a.g_is_self,a.g_exfield,a.g_hot';
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
            })
            ->select();
        $total = $query->where($where)->where($tag_where)->where($wherestr)->count();

        //$ApiRedis->set($redis_key, $lists, 60);
        //}

        $rsps['data'] = $lists;
        $rsps['total'] = $total;
        $rsps['tags'] = self::getTags($where);
        return $rsps;
    }

    /**
     * 游戏首页分类
     * @return array
     */
    public function game_type()
    {
        $ApiRedis = new ApiRedis();
        $redis_key = 'v2_game_type';
//        $data = $ApiRedis->get($redis_key);
//        if (!empty($data)) {
//            $rsps['code'] = 1;
//            $rsps['data'] = $data;
//            return $rsps;
//        }
        $GameTypeModel = new GameTypeModel();
        $data = $GameTypeModel->alias('a')
            ->where('a.type', '>', 0)
            ->order('a.type asc')
            ->field('a.name, a.id,a.type,a.icon, a.default_icon')
            ->select();
        $temp_data = [];
        foreach ($data as $k => $v) {
            if (!isset($temp_data[$v['type'] - 1])) {
                $temp_data[$v['type'] - 1] = [];
            }
            if ($v['type'] == 1) {
                $temp_data[$v['type'] - 1][] = [
                    'id' => 'hot',
                    'name' => '热门游戏',
                    'icon' => '/static/admin/img/hot.png',
                    'default_icon' => '/static/admin/img/hot.png',
//                    'game_count' => GamesModel::where('g_hot', 1)->count()
                ];
                $temp_data[$v['type'] - 1][] = $v;
                $temp_data[$v['type'] - 1][] = [
                    'id' => 'new',
                    'name' => '最新热门',
                    'icon' => '/static/admin/img/new.png',
                    'default_icon' => '/static/admin/img/new.png',
//                    'game_count' => GamesModel::where('g_is_new', 1)->count()
                ];
            } else {
                $temp_data[$v['type'] - 1][] = $v;
            }
        }
        $ApiRedis->set($redis_key, $temp_data, 180);
        $rsps['code'] = 1;
        $rsps['data'] = $temp_data;
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

    /**pwa 应用
     * @return array
     */
    public function pwaapp_run()
    {
//        {
//            "name": "92e BET",
//  "short_name": "92e BET",
//  "start_url": "/",
//  "icons": [
//    {
//        "src": "/a79/m-logo.png",
//      "sizes": "144x144",
//      "type": "image/png"
//    }
//  ],
//  "background_color": "#000000",
//  "theme_color": "#000000",
//  "display": "standalone"
//}
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $data = [
            'name' => $sys['website_name'],
            'short_name' => $sys['website_name'],
            'start_url' => '/',
            'icons' => [
                'src' => $sys['website_desktop_logo'],
                "sizes" => "144x144",
                "type" => "image/png"
            ],
            "background_color" => "#000000",
            "theme_color" => "#000000",
            "display" => "standalone"
        ];
        return $data;
    }

    /**
     * @param int $is 1 热门 2最佳 3最新 4内部
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
        if ($is == 1) {
            $where[] = ['g_hot', '=', 1];
        } elseif ($is == 2) {
            $where[] = ['g_is_optimum', '=', 1];
        } elseif ($is == 3) {
            $where[] = ['g_is_new', '=', 1];
        } else {
            $where[] = ['g_is_self', '=', 1];
        }
        $where_country = '';
        if (!empty($lang)) {
            $where_country = 'c.country not like "%' . $lang . '%" or c.country is null';
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
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->where($where)
            ->where($where_country)
            ->limit($limit)
            ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_tcid,a.g_state as game_status,a.g_is_self,a.g_exfield,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code,c.tc_name,a.label')
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
                return $data;
            })
            ->select();

        $total = Db::name('games')
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->where($where)
            ->where($where_country)->count();

        return ['list' => $list, 'total' => $total];
    }

    /**
     * @param int $type 前端平台id
     * @param int $limit 获取游戏条数
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    private function getGameListByType(int $type = 1, int $limit = 11): array
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [
            ['g_state', '>', 0],
            ['g_show', '=', 1],
            ['g_is_del', '=', 0]
        ];

        $where[] = ['g_type', '=', $type];
        $where_country = '';
        if (!empty($lang)) {
            $where_country = 'c.country not like "%' . $lang . '%" or c.country is null';
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
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->where($where)
            ->where($where_country)
            ->limit($limit)
            ->field('a.g_id as id,a.g_icon as icon,a.g_type,a.g_tcid,a.g_state as game_status,a.g_is_self,a.g_exfield,i.gi_name as game_name,i.gi_info,i.gi_headimg,i.gi_content,c.tc_code,c.tc_name,a.label')
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
                return $data;
            })
            ->select();

        $total = Db::name('games')
            ->alias('a')
            ->join('game_infos i', 'a.g_id = i.gi_gid')
            ->join('third_configs c', 'a.g_tcid = c.tc_id')
            ->where($where)
            ->where($where_country)->count();

        return ['list' => $list, 'total' => $total];
    }

}
