<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\GameTypeModel;
use think\facade\Request;

class GameSetFacctory
{
    private $uid;//用户id

    //验证
    public function __construct($uid = 0, GameTypeModel $GameTypeModel = null)
    {
        $this->uid = $uid;
        $this->GameTypeModel = $GameTypeModel;
    }

    public function type()
    {
        $param = Request::param();
        $limit = $param['limit'] ?? 10;
        $where = [];
        if (isset($param['state']) && $param['state'] != '') {
            $where[] = ['state', '=', intval($param['state'])];
        }
        if (isset($param['keyword']) && $param['keyword'] != '') {
            $where[] = ['name', 'LIKE', "%{$param['keyword']}%"];
        }
        $data = $this->GameTypeModel->where($where)->order("sort desc,id desc")->paginate($limit, false, ['query' => $param]);
        foreach ($data as $key => &$val) {
            $val['icon'] = Util::img_url($val['icon']);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}