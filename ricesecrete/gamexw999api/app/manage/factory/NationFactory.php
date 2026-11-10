<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\NationModel;
use app\common\model\LangModel;
use app\common\redis\CommomRedis;
use think\Request;
use think\facade\Config;

class NationFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0, NationModel $NationModel){
        $this->uid=$uid;
        $this->NationModel = $NationModel;
    }

    public function index()
    {
        $request = new Request();
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        $langs = $CommomRedis->getLangsData();
        $limit = $param['limit'] ?? 10;
        $data = $this->NationModel
            ->order('sort desc')
            ->filter(function($data) use ($langs){
                $arr = [];
                foreach($data['lang'] as $k => $v){
                    foreach($langs as $val){
                        if($val['id'] == $v){
                            $arr[$k] = $val['lang'];
                        }
                    }
                }
                $data['langs'] = $arr;
            })
            ->paginate($limit,false,['query' => $param]);

        $code = 1;
        $currency = Config::get('custom.country');
        $langs = LangModel::field('id,lang,is_default')
                ->where(['status' => 1])
                ->order("sort desc")
                ->select();
        return Respons::run(compact('code','data', 'currency', 'langs'));
    }
}