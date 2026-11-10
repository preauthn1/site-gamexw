<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\SignModel;
use app\common\model\SignDayModel;
use app\common\model\SignRecordModel;
use app\common\Utils;
use app\common\validate\TaskValidate;
use app\manage\factory\SignRecordFactory;
use think\exception\ValidateException;
use think\facade\Db;
use think\Request;
class Sign
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $signModel =new SignModel();
        //签到天数
        if( isset($param['sign_day']) && $param['sign_day'] !== '' ){
            $condition[] = ['sign_day', '=', $param['sign_day']];
        }
        $condition1 = '';
        //签到条件筛选
        if( isset($param['sign_condition']) && $param['sign_condition'] !== '' && in_array($param['sign_condition'], SignModel::getConditionArr()) ){
            $condition[] = ['sign_condition', '=', $param['sign_condition']];
        }
        //签到条件筛选
        if( isset($param['condition_date_type']) && $param['condition_date_type'] !== '' && in_array($param['condition_date_type'], SignModel::getConditionDateArr()) ){
            $condition[] = ['condition_date_type', '=', $param['condition_date_type']];
        }
        //语言筛选
        $paginate=$signModel
            ->where($condition)->where($condition1)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['sign'] = Utils::getOptions('sign', ['id', 'sign_day']);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            //同种类型的签到不能创建多个
            if ( $result = $this->validateAddOrEdit($info) ){
                return $result;
            }
            $res = SignModel::insertGetId($info);
            //循环生成对应天数
            $dayAllData = [];
            for ($i = 1; $i <= $info['sign_day']; $i++){
                $arr = [];
                $arr['sign_id'] = $res;
                $arr['day'] = $i;
                $arr['reward_mode'] = $info['reward_mode'];
                $arr['reward_type'] = $info['reward_type'];
                $arr['reward_amount'] = $info['reward_amount'];
                $arr['reward_integral'] = $info['reward_integral'];
                $arr['savings'] = $info['savings'];
                $dayAllData[] = $arr;
            }
            //添加对应天数
            (new SignDayModel())->saveAll($dayAllData);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new SignModel(),lang('签到添加'));
        }
    }

    //
    public function validateAddOrEdit($info){
        if( $info['sign_condition'] == SignModel::CONDITION_APPOINT ){
            $condition = [];
            $condition[] = ['lang', '=', $info['lang']];
            $condition[] = ['sign_condition', '=', SignModel::CONDITION_APPOINT];
            $condition[] = ['sign_id', '=', $info['sign_id']];
        }else{
            $condition = [];
            $condition[] = ['lang', '=', $info['lang']];
            $condition[] = ['sign_condition', '<>', SignModel::CONDITION_APPOINT];
        }
        if( isset($info['id']) && $info['id'] ){
            $condition[] = ['id', '<>', $info['id']];
        }
        $detail = SignModel::where($condition)->find();
        if( $detail ){
            return Respons::run(0, [], '相同类型只能创建一个');
        }
        return null;
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $signModel = new SignModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            //同种类型的签到不能创建多个
            if ( $result = $this->validateAddOrEdit($info) ){
                return $result;
            }
            $data = $signModel->where('id',$info['id'])->find();
            $res=$signModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$signModel,lang('签到修改'));
        }
        if (isset($param['find'])) {
            $res=$signModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $signModel =new SignModel();
            $res=$signModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$signModel,lang('签到删除'));
        }
    }

    //签到记录
    public function recordLists(Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $signRecordModel =new SignRecordModel();
        //UID
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['uid', '=', $param['uid']];
        }
        //签到天数
        if( isset($param['day']) && $param['day'] !== '' ){
            $condition[] = ['day', '=', $param['day']];
        }
        //状态
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        //签到状态
        if( isset($param['sign_status']) && $param['sign_status'] !== '' ){
            $condition[] = ['sign_status', '=', $param['sign_status']];
        }
        //是否过期
        if( isset($param['is_expired']) && $param['is_expired'] !== '' ){
            $condition[] = ['is_expired', '=', $param['is_expired']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        //导出数据
        if (isset($param['export']) && $param['export'] == 1) {
            return (new SignRecordFactory($request->token->uid))->exportList($condition,'');
        }
        //语言筛选
        $paginate=$signRecordModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        //统计
        $statistics = [
            'finish_user_count' => $signRecordModel->group('uid')->count(),//签到用户数量
            'send_user_count' => $signRecordModel->where('is_receive_reward', '=', 1)->group('uid')->count(),//发放用户数量
            'finish_amount' => $signRecordModel->sum('reward_amount'),//签到金额
            'send_amount' => $signRecordModel->where('is_receive_reward', '=', 1)->sum('reward_amount'),//发放金额
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }
}