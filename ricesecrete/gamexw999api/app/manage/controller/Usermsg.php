<?php
namespace app\manage\controller;
use app\common\model\UserMsgModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\UserModel;
use app\common\model\ActionModel;
use app\common\model\UserTagModel;
use app\common\service\MessageService;
use app\manage\factory\UsermsgFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
use think\facade\Queue;
class Usermsg
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new UsermsgFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info  =$param['info'];
            try {
                validate(\app\common\validate\UserMsgValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0,[],$e->getError());
            }
            $items = [];
            if ($info['send_type'] == 1) {
                $where[] = ['tree', 'like', '%/'.$info['id'].'/%'];
                $UserModel = new UserModel();
                $list = $UserModel->where($where)->field('id')->select();
                if (isset($list[0]['id'])) {
                    foreach($list as $key => $val) {
                        $items[$key]['lang'] = $info['lang'];
                        $items[$key]['add_time'] = time();
                        $items[$key]['uid'] = $val['id'];
                        $items[$key]['title'] = $info['title'];
                        $items[$key]['content'] = $info['content'];
                        $items[$key]['img'] = $info['img']??'';
                        $items[$key]['action_id'] = $info['action_id'] ?? 0;
                        $items[$key]['type'] = 3;
                        $items[$key]['link'] = $info['link'] ?? '';
                        $items[$key]['link_title'] = $info['link_title'] ?? '';
                    }
                }else{
                    return Respons::run(0,[],'代理线不存在');
                }
            }
            elseif($info['send_type'] == 2) {
                $data = [
                    'lang' => $info['lang'],
                    'add_time' => time(),
                    'uid' => 0,
                    'title' => $info['title'],
                    'content' => $info['content'],
                    'img' => $info['img']??'',
                    'type' => 13,
                    'action_id' => $info['action_id'] ??0,
                    'link' => $info['link'] ?? '',
                    'link_title' => $info['link_title'] ?? '',
                ];
                $info = UserMsgModel::create($data);
                if (!$info) {
                    return Respons::run(0,[],'系统消息发送失败');
                } else {
                    MessageService::sendUserGroupMsg($info['content'], 'pop', 0, $info['title']);
                    return json(["code" => 1, 'msg' => '发送成功']);
                }
            }
            else {
                $ids = array_unique(array_filter(explode(",", $info['id'])));
                if (isset($info['tag_id']) && $info['tag_id']) {
                    $uids = UserTagModel::where('tag_id', $info['tag_id'])->column('uid');
                    $ids = array_unique(array_merge($ids, $uids));
                }
                foreach ($ids AS $id) {
                    $items[] = [
                        'lang' => $info['lang'],
                        'add_time' => time(),
                        'uid' => $id,
                        'title' => $info['title'],
                        'content' => $info['content'],
                        'img' => $info['img']??'',
                        'tag_id' => $info['tag_id']??'',
                        'type' => $info['send_type'] == '2' ? 13:3,
                        'action_id' => $info['action_id'] ?? 0,
                        'link' => $info['link'] ?? '',
                        'link_title' => $info['link_title'] ?? '',
                    ];
                }
            }
            $send_num = count($items);
            if( $items ){
                (new UserMsgModel())->saveAll($items);
            }
            try{
                foreach($items as $key => $value){
                    $value['send_num'] = $send_num;
                    $value['current'] = $key;
                    $value['admin_name'] = Session::get("adminInfo.name");
                    MessageService::sendMsg($value['content'], $value['uid'], 'toast', 5, $value['title']);
//                    Queue::push('SendMsg@usermsg', $value, 'import');
                }
                return json(["code" => 1, 'msg' => '发送成功']);
            }catch (\Throwable $t){
                return Respons::run(0,[],$t->getMessage());
            }
        }
    }

    //编辑
    public function edit(Request $request){
    	$param = $request->param();
        $userMsgModel = new UserMsgModel();
    	if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $userMsgModel->where('id',$info['msg_id'])->find();
            $ids = explode(',', $info['id']);
            unset($info['id']);
            unset($info['msg_id']);
            $res=$data->save($info);
            try{
                $send_num = count($ids);
                foreach($ids as $key => $id){
                    $info['send_num'] = $send_num;
                    $info['current'] = $key;
                    $info['admin_name'] = Session::get("adminInfo.name");
                    $info['uid'] = $id;
                    MessageService::sendMsg($info['content'], $info['uid'], 'toast', 5, $info['title']);
//                    Queue::push('SendMsg@usermsg', $info, 'import');
                }
            }catch (\Throwable $t){
                return Respons::run(0,[],$t->getMessage());
            }
            return Respons::run($res?1:0,'','发送成功',$data,$info,$userMsgModel,lang('站内信修改'));
        }
        if (isset($param['find'])) {
            $res=$userMsgModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $UserMsgModel =new UserMsgModel();
            $res=$UserMsgModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$UserMsgModel,lang('站内信删除'));
        }
    }
}