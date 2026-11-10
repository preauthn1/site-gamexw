<?php
namespace app\manage\controller;
use app\common\lib\ReportExport;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\JackpotModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\RocketActivityRecordModel;
use app\common\Utils;
use app\manage\factory\JackpotFactory;
use think\Request;
class JackpotRankings
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $jackpotModel =new JackpotModel();
        $condition = [];
        //日期
        if( isset($param['day']) && $param['day'] ){
            $condition[] = ['j.day', '=', $param['day']];
        }
        //类型
        if( isset($param['j_type']) && $param['j_type'] !== '' ){
            $condition[] = ['j.j_type', '=', $param['j_type']];
        }
        //排名
        if( isset($param['ranking']) && $param['ranking'] !== '' ){
            $condition[] = ['j.ranking', '=', $param['ranking']];
        }
        //是否假数据
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['j.type', '=', $param['type']];
        }
        //用户筛选
        if( isset($param['username']) && $param['username'] ){
            if( is_numeric($param['username']) ){
                $condition[] = ['u.id|u.phone|u.rec_code', '=', $param['username']];
            }else{
                $condition[] = ['u.username|u.mail|j.username', 'like', '%'. $param['username']. '%'];
            }
        }
        //最低金额
        if( isset($param['min_amount']) && $param['min_amount'] !== '' ){
            $condition[] = ['j.price', '>=', $param['min_amount']];
        }
        //最高金额
        if( isset($param['max_amount']) && $param['max_amount'] !== '' ){
            $condition[] = ['j.price', '<=', $param['max_amount']];
        }
        //最低假数据金额
        if( isset($param['upper_min_amount']) && $param['upper_min_amount'] !== '' ){
            $condition[] = ['j.upper_limit', '>=', $param['upper_min_amount']];
        }
        //最高假数据金额
        if( isset($param['upper_max_amount']) && $param['upper_max_amount'] !== '' ){
            $condition[] = ['j.upper_limit', '<=', $param['upper_max_amount']];
        }

        if( isset($param['export']) && $param['export'] == 1 ){
            return (new JackpotFactory($request->token->uid))->exportList($condition);
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'j.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'j.id desc';
        }

        $rsps['data']=$jackpotModel->alias('j')
            ->join('user u', 'u.id=j.uid', 'left')
            ->where($condition)
            ->field('j.*,u.mail,u.phone,u.login_ip_count')
            ->order($order)
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            if( $info['create_num'] > 500 ){
                return Respons::run(0, [], '每次最多生成500条数据');
            }
            $saveAll = [];
            $time = time();
            //根据类型获取查询时间和当期时间
            $timeResult = JackpotModel::getTimeByJType($info['j_type']);
            for ($i=0; $i < $info['create_num']; $i++){
                $arr = [];
                $arr['j_type'] = $info['j_type'];
                $arr['uid'] = '-'.$info['j_type'].date('Hi').$i.rand(100, 999);#假数据必须以-开头，且日周月不能重复
                $arr['username'] = Util::random(10);
                $arr['head_img'] = $info['head_img'] ?: DefaultHeadModel::getHeadOne();
                $arr['price'] = $info['price'];
                $arr['type'] = 2;
                $arr['day'] = $timeResult['day'];
                $arr['min_rand'] = $info['min_rand'];
                $arr['max_rand'] = $info['max_rand'];
                $arr['upper_limit'] = $info['upper_limit'];
                $arr['create_time'] = $arr['update_time'] = $time;
                $saveAll[] = $arr;
            }
            if( $saveAll ){
                (new JackpotModel())->insertAll($saveAll);
            }
            //添加完后计算排名
            JackpotModel::rank_statistics($info['j_type']);
            return Respons::run(1,'','','',$info,new JackpotModel(),lang('jackpot排行榜添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $jackpotModel=new JackpotModel();
            $data = $jackpotModel->where('id',$info['id'])->find();
            $info['update_time'] = time();
            $res=$jackpotModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$jackpotModel,lang('jackpot排行榜编辑'));
        }
        if (isset($param['find'])) {
            $jackpotModel =new JackpotModel();
            $res=$jackpotModel->where("id",$param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $jackpotModel =new JackpotModel();
            $res=$jackpotModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$jackpotModel,lang('jackpot排行榜删除'));
        }
    }
}