<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\BankSkModel;
use app\common\model\PayTypeModel;
use app\common\model\UserModel;
use app\common\model\LangModel;
use app\common\model\UserLevelModel;
use app\common\Utils;
use think\facade\Request;
use think\facade\Config;

class BankSkFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $paytype = Util::getOfflinePayType();
        $type = isset($param['type'])?$param['type']:0;
        if(!isset($paytype[$type])){
            $type = key($paytype);
        }
        $limit=$param['limit'] ?? 10;
        $where=[];
        $BankSkModel = new BankSkModel();
        $where[] = ["type", "=", $type];
        if (isset($param['title']) && $param['title']!='') {
            $where[]=["title","like","%".$param['title']."%"];
        }
        $rsps['data'] = $BankSkModel
            ->where($where)
            ->with('currency')
            ->order("bank_sort desc,id desc")
            ->paginate($limit,false,['query' => $param]);
        $levelNameArr = UserLevelModel::where("1=1")->column('level_name', 'id');
        foreach($rsps['data'] as &$val){
            $val['add_time'] =  date("Y-m-d H:i:s",$val['add_time']);
            if($val['agent_id'] > 0){
                $val['agent_name'] = UserModel::where(['id' => $val['agent_id']])->value('username');
            }else{
                $val['agent_name'] = "";
            }
            $val['start_level_name'] = $levelNameArr[$val['start_level_id']] ?? '不限制';
            $val['end_level_name'] = $levelNameArr[$val['end_level_id']] ?? '不限制';
        }
        $financial_status = 0;
        if($type == 0){ //银行卡的时候 把金融编码展示出来
            $financial = Config::get('custom.financial');
            $defa_lang = LangModel::where(['is_default'=>1])->value('lang');
            if(isset($financial[$defa_lang])){
                $financial_status = 1;
            }
        }
        $rsps['code'] = 1;
        $rsps['currency'] = Util::getCurrency();
        $rsps['pix_type'] = Config::get('custom.pix_type');
        $rsps['financial_status'] = $financial_status;
        $rsps['vip'] = UserLevelModel::where("1=1")->column("id,level_name");
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['pay_types'] = PayTypeModel::where("1=1")->column("id,name");
        return Respons::run($rsps);
    }
}