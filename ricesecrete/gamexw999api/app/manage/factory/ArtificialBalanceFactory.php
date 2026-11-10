<?php

namespace app\manage\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserModel;
use think\facade\Request;
use app\common\Utils;

class ArtificialBalanceFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $accurate = isset($param['accurate'])?$param['accurate']:0;
        $where=[];
        if (isset($param['username']) && $param['username']!='') {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","in", $uids];
        }

        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($time[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        if (isset($param['lebal']) && $param['lebal']!='') {
            $where[]=["a.lebal","=",$param['lebal']];
        }

        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }

        if(isset($param['admin_name']) && $param['admin_name']!=''){
            $where[] = ['a.admin_name', 'like', '%' . $param['admin_name'] . '%'];
        }

        if(isset($param['a_type']) && $param['a_type'] > 0){
            $where[] = ['a.a_type','=',$param['a_type']];
        }else if(isset($param['a_types']) && $param['a_types'] > 0){
            $where[] = ['a.a_type','=',$param['a_types']];
        }

        $rsps['data'] = ArtificialBalanceModel::alias("a")
            ->where($where)
            ->join('user b', 'b.id=a.uid')
            ->order("a.id desc")
            ->field("a.*,b.username,b.mail,b.phone")
            ->paginate($limit,false,['query' => $param]);

        foreach($rsps['data'] as &$val){
            $val['add_time'] =  date("Y-m-d H:i:s",$val['add_time']);
            $val['msg'] = empty($val['msg'])?"":lang($val['msg']);
            $val = Utils::hideUserInfo($val);
        }
        $rsps['code']=1;
        $rsps['pending_price'] = sprintf("%.2f", ArtificialBalanceModel::alias("a")->where($where)->where('a.a_type=1')->sum('a.price'));
        $rsps['distribute_price'] = sprintf("%.2f", ArtificialBalanceModel::alias("a")->where($where)->where('a.a_type=2')->sum('a.price'));
        $rsps['refuse_price'] = sprintf("%.2f", ArtificialBalanceModel::alias("a")->where($where)->where('a.a_type=3')->sum('a.price'));
        $rsps['level_price'] = sprintf("%.2f", ArtificialBalanceModel::alias("a")->where($where)->where('a.a_type=4')->sum('a.price'));
        $rsps['rebate_price'] = sprintf("%.2f", ArtificialBalanceModel::alias("a")->where($where)->where('a.a_type=5')->sum('a.price'));
        $detail_notes = Utils::sysInfo("detail_notes");
        $detail_notes = empty($detail_notes)?[]:unserialize($detail_notes);
        $rsps['detail_notes'] = $detail_notes;
        //模板下载路径
        $rsps['temp_url'] = (new ApiRedis())->systemInfo()['domain'].'/temp/temp.xlsx';
        return Respons::run($rsps);
    }
}