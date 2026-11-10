<?php
namespace app\admin\controller;

use app\api\factory\GameFactory;
use app\common\Utils;
use app\common\model\{GameSetModel, GameUserModel, PaySetModel};
use think\facade\{Config, View, Request};
use app\common\lib\Respons;

class GameUser
{	
	protected $middleware = ['adminCheckLogin',"Language"];

    /**
     * @var GameSetModel
     */
    private  $GameUserModel;

    public function __construct(GameUserModel $GameUserModel){
        $this->GameUserModel = $GameUserModel;
    }

    //列表
    public function list(){
        $param = Request::param();
        if (isset($param['select'])) {
            $limit = $param['limit'];
            $where = [];
            $where['a.online'] = $param['online'] ?? '';
            $where['b.game_sys_sign'] = $param['game_sys_sign'] ?? '';
            $where = array_filter($where);
            $rsps['data'] = $this->GameUserModel
                ->alias("a")
                ->join("dq_game_set b","a.game_id=b.id")
	            ->where($where)
                ->when(!empty($param['username']), function($query)  use($param){
                    $query->where('a.username|a.member_id', 'like', '%'.$param['username'].'%');
                })
                ->when(!empty($param['last_login_time']), function($query) use($param){
                    $date = explode(' - ',$param['last_login_time']);
                    $query->whereBetweenTime('last_login_time', Utils::getQueryDateTime($date[0]), Utils::getQueryDateTime($date[1], true));
                })
                ->when(!empty($param['game_code']), function($query)  use($param){
                    $query->where(['b.game_code' => $param['game_code']]);
                })
	            ->order("a.id desc")
                ->field("a.*,b.game_name,b.game_sys_sign,b.wallet_type")
                ->filter(function($data){
                    $data['add_time'] = $data['add_time'] ? date('Y-m-d H:i:s', $data['add_time']) : '';
                    $data['last_login_time'] = $data['last_login_time'] ?  date('Y-m-d H:i:s', $data['last_login_time']) : '';
                })
                ->paginate($limit,false,['query' => $param]);
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }

        $arr = [0];
        $type = array_diff(config::get('custom.three_game'),$arr);
        $group = config::get('custom.three_game_group');
        $data = [];
        foreach($type as $k => $v){
            $data[$k] = $group[$k];
        }
        View::assign('type', $data);
        return View::fetch('game_user/list');
    }

}