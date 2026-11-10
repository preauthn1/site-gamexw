<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\CurrencyModel;
use think\facade\Config;
use think\facade\Request;

class CurrencyFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid, CurrencyModel $CurrencyModel){
        $this->uid=$uid;
        $this->CurrencyModel = $CurrencyModel;
    }

    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'] ?? 10;
        $where = [];
        if (isset($param['name']) && $param['name'] != '') {
            $where[] = ['name|country_code','like',"%{$param['name']}%"];
        }

        if (isset($param['state']) && $param['state'] != '') {
            $where[] = ['state' ,'=', $param['state']];
        }

        if (isset($param['is_defa']) && $param['is_defa'] != '') {
            $where[] = ['is_defa' ,'=', $param['is_defa']];
        }

        $data = $this->CurrencyModel
            ->where($where)
            ->order('id DESC')
            ->paginate($limit,false,['query' => $param]);
        $code = 1;
        $usdt_type = Config::get('custom.usdt_type');
        $country = Config::get('custom.country');
        return Respons::run(compact('code', 'data', 'usdt_type', 'country', 'where'));
    }
}