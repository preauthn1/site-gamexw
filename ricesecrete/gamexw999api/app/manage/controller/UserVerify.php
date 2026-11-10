<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\UserLevelModel;
use app\common\model\UserModel;
use app\common\model\UserVerifyConfigModel;
use app\common\model\UserVerifyModel;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use app\common\Utils;
use app\manage\factory\UserVerifyFactory;
use think\Request;

class UserVerify
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $user_verify_model = new UserVerifyModel();
        if (isset($param['status']) && $param['status'] !== '') {
            $condition[] = ['uv.status', '=', $param['status']];
        }
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $condition[]=['u.id|u.phone|u.rec_code|u.phone',"=", $param['username']];
            } else {
                $condition[]=['u.username|u.mail',"like",'%'.$param['username'].'%'];
            }
        }
        if (isset($param['search_key']) && $param['search_key'] !== '') {
//            $searchValue = '"key":"' . $param['search_key'] . '","type":"text","value":"' . $param['search_value'] . '"';
            $searchValue = '"key":"' . $param['search_key'] . '","type":"text",';
            $condition[] = ['uv.content', 'like', '%'.$searchValue.'%'];
            $condition[] = ['uv.content', 'like', '%'.$param['search_value'].'%'];
        }
        $rsps = $user_verify_model->alias('uv')
            ->join('user u', 'u.id = uv.uid')
            ->field('uv.*,u.id as uid,u.lebal,u.phone,u.mail,login_ip_count')->where($condition)
            ->order("uv.id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps = json_decode(json_encode($rsps),true);
        $UserLevelModel = new UserLevelModel();
        $UserModel = new UserModel();
        $user_level_data = $UserLevelModel->column('level_name', 'id');
        foreach($rsps['data'] as $k => $value){
            $user_data = $UserModel->where('id',$value['uid'])->find();
            $rsps['data'][$k]['tags'] = MemberService::getTagByUid($value['uid']);
            $rsps['data'][$k]['rec_code'] = $user_data['rec_code'];
            $rsps['data'][$k]['level_name'] = $user_level_data[$user_data['level_id']];
            $rsps['data'][$k]['username'] = MemberService::getUsernameByUid($value['uid']);
        }
        $outdata['data'] = $rsps;
        $outdata['code'] = 1;

        //筛选数据类型
        $outdata['search_key'] = Utils::getOptions('user_verify_config', ['keyv', 'name'], [['status', '=', 1],['type', '=', 'text']]);
        //实名认证快捷备注
        $user_verify_notes = Utils::sysInfo("user_verify_notes");
        $user_verify_notes = empty($user_verify_notes)?[]:unserialize($user_verify_notes);
        $outdata['user_verify_notes'] = $user_verify_notes;
        return Respons::run($outdata);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            //判断是否添加了认证中或者已认证数据
            $condition = [];
            $condition[] = ['uid', '=', $info['uid']];
            if( UserVerifyModel::where($condition)->value('id') ){
                return Respons::run(0, [], '该会员已有实名认证数据');
            }
            //判断用户是否存在
            if( !UserModel::where('id', $info['uid'])->value('id') ){
                return Respons::run(0, [], '该会员不存在');
            }

            unset($info['id']);
            if( !isset($info['content_json']) || !$info['content_json'] ){
                //查询需要配置的字段

                $verifyConfig = UserVerifyConfigModel::where('status', '1')->order('sort asc')->select();
                //获取用户验证数据
                $config_data = [];
                foreach ($verifyConfig as $value) {
                    $config_data[] = [
                        'name' => $value['name'],
                        'key' => $value['keyv'],
                        'type' => $value['type'],
                        'value' => "",
                    ];
                }
                $info['content'] = json_encode($config_data);
            }else{
                $info['content'] = $info['content_json'];
            }
            unset($info['content_json']);
            $res = UserVerifyModel::insertGetId($info);
            //处理状态操作
            UserVerifyFactory::handleVerifyStatus($info['uid'], $info['status'], $info['remarks'] ?? '');
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new UserVerifyModel(), lang('添加实名认证数据'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $user_verify_model = new UserVerifyModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $info['content'] = json_encode($info['content_json'], JSON_UNESCAPED_UNICODE);
            $data = $user_verify_model->where('id', $info['id'])->find();
            $res = $user_verify_model->update($info);
            //处理状态操作
            UserVerifyFactory::handleVerifyStatus($data['uid'], $info['status'], $info['remarks'] ?? '');
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $user_verify_model, lang('修改实名认证数据'));
        }
        if (isset($param['find'])) {
            $res = $user_verify_model->where("id", $param['id'])->find();
            if(!empty($res['content'])){
                $res['content_json'] = json_decode($res['content'],true);
            }else{
                $res['content_json'] = [];
            }
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $user_verify_model = new UserVerifyModel();
            $res = $user_verify_model->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $user_verify_model, lang('删除实名认证数据'));
        }
    }
}