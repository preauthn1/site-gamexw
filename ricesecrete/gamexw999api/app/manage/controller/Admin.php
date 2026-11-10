<?php
namespace app\manage\controller;

use app\common\model\AdminModel;
use app\common\model\AdminNodeModel;
use app\common\model\AdminRoleModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\service\MessageService;
use app\manage\factory\AdminFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
class Admin extends Base
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    public function lists(\think\Request $request)
    {
        $rsps = (new AdminFactory($request->token->uid))->lists();
        return $rsps;
    }

	//列表
    public function list(AdminModel $AdminModel){

        if (isset($param['role'])) {
            [$admin_id,$role_id] = [
                Session::get("adminInfo.id"),
                Session::get("adminInfo.role_id")
            ];
            /** @var AdminRoleModel $AdminRoleModel*/
            $AdminRoleModel = app()->make(AdminRoleModel::class);
            $data = $AdminRoleModel
                    ->when($role_id > 1, function($query) use($admin_id){
                        $query->where(['admin_id' => $admin_id]);
                    })->select();
            $code = 1;
            return Respons::run(compact('code','data'));
        }
        $parent_arr = $AdminModel
                    ->with('adminParent')
                    ->where(['id' => $this->user_id])
                    ->field('id, parent_id')
                    ->find();
        //TODO:直接获取四层 后面有好的方法再优化
        $is_add = true;
        if(isset($parent_arr['adminParent']['adminParent']['adminParent']['adminParent'])){
            $is_add = false;
        }
        View::assign("id",Session::get("adminInfo.id"));
        View::assign("is_add",$is_add);
        return View::fetch('admin/list');
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\AdminValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0,[],$e->getError());
            }
            //验证密码格式
            $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9a-zA-Z]{8,16}$/';
            if(!preg_match($pattern,$info['password'])){
                return Respons::run(0,[],lang('登录密码包含字母和数字，长度8-16位！'));
            }
            $info['password']=Util::md5Password($info['password']);
            $AdminModel =new AdminModel();
            $admin=$AdminModel->where("username",$info['username'])->field("id")->find();
            if (isset($admin['id'])) {
                return Respons::run(0,[],lang('账号已存在'));
            }
            $info['parent_id'] = $request->token->uid;
            $res=$AdminModel->insertGetId($info);
            $info['id'] = $res;
            $info['role_id'] = AdminRoleModel::where('id',$info['role_id'])->value('role_name');
            return Respons::run($res?1:0,'','','',$info,$AdminModel,lang('管理员账号添加'));
        }
        if (isset($param['password'])) { 
            $data = get_rand_char();
            return Respons::run(1, $data);
        }
    }

    //编辑
    public function edit(Request $request){
    	$param = $request->param();
    	if (isset($param['edit'])) {
            $info=$param['info'];
            try {
                validate(\app\common\validate\AdminValidate::class)->scene('edit')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0,[],$e->getError());
            }
            $reset_google = $info['reset_google'];
            unset($info['reset_google']);
            if($reset_google == 1){
                $info['goodsle_bind'] = 0;
                $info['google_secret'] = '';
            }
            
            if(!empty($info['password'])){
               //验证密码格式
                $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9a-zA-Z]{8,16}$/';
                if(!preg_match($pattern,$info['password'])){
                    return Respons::run(0,[],lang('登录密码包含字母和数字，长度8-16位！'));
                }
                $info['password']=Util::md5Password($info['password']);
            }else{
                unset($info['password']);
            }
            
            $AdminModel =new AdminModel();
            $data = $AdminModel->where('id',$info['id'])->find();
            $data['role_id'] = AdminRoleModel::where('id',$data['role_id'])->value('role_name');
            $res=$AdminModel->update($info);
            $info['role_id'] = AdminRoleModel::where('id',$info['role_id'])->value('role_name');
            $info['reset_google'] = $reset_google;
            return Respons::run($res?1:0,'','',$data,$info,$AdminModel,lang('管理员账号修改'));
    	}
    	if (isset($param['find'])) {
            $AdminModel =new AdminModel();
            $res=$AdminModel->where("id",$param['id'])->withoutField('password')->find();
            $res['is_my'] = 2;
            if(Session::get("adminInfo.id") == $res['id'] &&  Session::get("adminInfo.role_id") != 1) {
                $res['is_my'] = 1;
            }
//            $res['role_id'] = Session::get("adminInfo.role_id");
            return Respons::run(1,$res);
    	}
    }

    //编辑状态
    public function status_edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = AdminModel::where('id',$info['id'])->field('id,status')->find();
            $AdminModel=new AdminModel();
            $res=$AdminModel->update($info);
            return Respons::run(1,'','',$data,$info,$AdminModel,lang('状态修改'));
        }
    }

    //修改密码
    public function edit_password(Request $request){
    	$param = $request->param();
    	if (isset($param['editPassword'])) {
            $info=$param['info'];
            //验证密码格式
            $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9a-zA-Z]{8,16}$/';
            if(!preg_match($pattern,$info['password'])){
                return Respons::run(0,[],lang('登录密码包含字母和数字，长度8-16位！'));
            }
            $info['password']=Util::md5Password($info['password']);
            $AdminModel =new AdminModel();
            $res=$AdminModel->update($info);
            return Respons::run($res?1:0,'','',$info,$info,$AdminModel,lang('管理员账号密码修改'));

        }
    }

    //删除
    public function delete(Request $request){
    	$param = $request->param();
    	if (isset($param['delete'])) {
            $id=$param['id'];
            $AdminModel =new AdminModel();
            $res=$AdminModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$AdminModel,lang('管理员账号删除'));
    	}
    }
    
    public function rand_char(){
    	$rsps['data'] = get_rand_char();
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    /** 递归拿到所有的id
     * @param object $ids 查出来的所有数据
     * @param int $id  当前用户id 用来拼接在最后
     * @return String
     */
    protected function getIdsData(object $ids,int $id) :String
    {
        function data($data,&$str)
        {
            foreach($data as $v){
                $str .= $v['id'] . ',';
                if(count($v['adminChildren']) > 0){
                    data($v['adminChildren'],$str);
                }
            }
//            $str =  trim($str,',');
        }
        $str = '';
        data($ids,$str);
        return $str.$id;
    }

    public function getMenu(){
        $role_permission = AdminRoleModel::where('id', $this->user['role_id'])->value('role_permission');
        $AdminNodeModel = new AdminNodeModel();

        $where = [
            ['status', '=', 1]
        ];
        if ($this->user['role_id'] > 1) {
            $where[] = ['id', 'in', $role_permission];
            $where[] = ['not', '=', 0];
        }
        //获取菜单
        $menu_node = $AdminNodeModel->where($where)
            //关联查询的时候 如果管理员id 是1 那就是超级管理员 能看到所有权限 否则只能看到不限制权限的
            //关联查询的时候 如果角色id 大于1 就是非系统管理员 获取角色的 否则直接获取全部
            ->with(['menuNode' => function($query) use($where){
                $query->where($where);
            }])
            ->where('pid',0)
            ->order('sort ASC,id DESC')
            ->field('node_name,icon,id,pid,not,node_url,sort,name,path')
            ->select();
        //非系统管理员获取权限节点的路径 用来判断显示操作按钮 系统管理员的时候 默认全部 存空数组
        $node_url_arr = [];
        if($this->user['role_id'] > 1){
            $node_url_arr = $AdminNodeModel
                ->where('id','in',$role_permission)
                ->column('node_url');
            $node_url_arr = array_filter($node_url_arr);
        }
        Session::set('node_url_arr', $node_url_arr);
        $data = [];

        foreach ($menu_node AS $node) {
            $item = [
                'path' => $node['path'],
                'meta' => [
                    'keepAlive' => true,
                    'title' => $node['node_name'],
                    'icon' => $node['icon'],
                    'rank' => $node['sort'] == 0 ? $node['id'] : $node['sort']
                ],
                'children' => []
            ];
            foreach ($node['menuNode'] AS $child_node) {
                $auths = $AdminNodeModel->where('pid', $child_node['id'])->where($where)->column('path');
                if ($child_node['path'] == '/threeGames/gamePlatform' && $this->user['role_id'] == 1) {
                    $auths[] = 'api';
                }
                $citem = [
                    'path' => $child_node['path'],
                    'name' => $child_node['name'],
                    'meta' => [
                        'showLink' => !in_array($child_node['path'], ['/weishang/reportdetail', '/weishang/viewreport']),
                        'keepAlive' => true,
                        'title' => $child_node['node_name'],
                        'icon' => $child_node['icon'],
                        'roles' => ["role{$this->user['role_id']}"],
                        'auths' => $auths
                    ],
                ];
                $item['children'][] = $citem;
            }
            $data[] = $item;
        }
        return Respons::run([
            'code' => 1,
            'data' => $data,
            'per' => $role_permission,
            'where0' => $where
        ]);
    }

    public function getNav() {
        $role_permission = AdminRoleModel::where('id', $this->user['role_id'])->value('role_permission');
        $AdminNodeModel = new AdminNodeModel();

        $where = [
            ['status', '=', 1]
        ];
        if ($this->user['role_id'] > 1) {
            $where[] = ['id', 'in', $role_permission];
            $where[] = ['not', '=', 0];
        }
        //获取菜单
        $menu_node = $AdminNodeModel->where($where)
            //关联查询的时候 如果管理员id 是1 那就是超级管理员 能看到所有权限 否则只能看到不限制权限的
            //关联查询的时候 如果角色id 大于1 就是非系统管理员 获取角色的 否则直接获取全部
            ->with(['menuNode' => function($query) use($where){
                $query->where($where);
            }])
            ->where('pid',0)
            ->order('sort ASC,id DESC')
            ->field('node_name,icon,id,pid,not,node_url,sort,name,path')
            ->select();

        //非系统管理员获取权限节点的路径 用来判断显示操作按钮 系统管理员的时候 默认全部 存空数组
        $node_url_arr = [];
        if($this->user['role_id'] > 1){
            $node_url_arr = $AdminNodeModel
                ->where('id','in',$role_permission)
                ->column('node_url');
            $node_url_arr = array_filter($node_url_arr);
        }
        Session::set('node_url_arr', $node_url_arr);
        $data = [];

        foreach ($menu_node AS $node) {
            $item = [
                'path' => $node['path'],
                'meta' => [
                    'showLink' => true,
                    'keepAlive' => true,
                    'title' => $node['node_name'],
                    'icon' => $node['icon'],
                    'rank' => $node['sort'] == 0 ? $node['id'] : $node['sort'],
                    "showLevel" => true,
                ],
                'children' => []
            ];
            foreach ($node['menuNode'] AS $child_node) {
                $auths = [];
                $is_son_count = $AdminNodeModel->where(['pid' => $child_node['id'], 'is_menu' => 0])->where($where)->count();
                if($is_son_count == 0){
                    $auths = $AdminNodeModel->where(['pid' => $child_node['id'], 'is_menu' => 1])->where($where)->column('path');
                    if ($child_node['path'] == '/threeGames/gamePlatform' && $this->user['role_id'] == 1) {
                        $auths[] = 'api';
                    }
                }

                $citem = [
                    'path' => $child_node['path'],
                    'name' => $child_node['name'],
                    'meta' => [
                        'showLink' => !in_array($child_node['path'], ['/weishang/reportdetail', '/weishang/viewreport']),
                        //'keepAlive' => true,
                        'title' => $child_node['node_name'],
                        'icon' => $child_node['icon'],
                        'roles' => ["role{$this->user['role_id']}"],
                        'auths' => $auths,
                        'parentName' => $node['name'],
                        "showLevel" => true,
                        //'activePath' => $child_node['path'],
                    ],
                ];

                if($is_son_count > 0) {
                    $citem['redirect'] = $citem['path'];
                    $citem['path'] = $citem['path']."1";
                    $menu_s = $AdminNodeModel->where('pid', $child_node['id'])->order('sort desc')->where($where)->select();
                    foreach ($menu_s as $key => $val) {
                        $is_son_count = $AdminNodeModel->where(['pid' => $val['id'], 'is_menu' => 0])->where($where)->count();
                        $auths = [];
                        if ($is_son_count == 0) {
                            $auths = $AdminNodeModel->where(['pid' => $val['id'], 'is_menu' => 1])->where($where)->column('path');
                        }
                        $scitem = [
                            'path' => $val['path'],
                            'name' => $val['name'],
                            'meta' => [
                                'showLink' => true,
                                'showParent' => true,
                                //'keepAlive' => true,
                                'title' => $val['node_name'],
                                'activePath' => $citem['path'],
                                'hidden' => true,
                                'icon' => $val['icon'],
                                'roles' => ["role{$this->user['role_id']}"],
                                'auths' => $auths,
                                'parentName' => $child_node['name'],
                                "showLevel" => false,
                            ],
                        ];
                        $citem['children'][] = $scitem;
                    }
                }
                $item['children'][] = $citem;
            }
            $data[] = $item;
        }
        return Respons::run([
            'code' => 1,
            'data' => $data,
            'per' => $role_permission,
            'where0' => $where
        ]);
    }

    public function getWsToken() {
        $uid = MessageService::registerMsgAdmin($this->user['id'], $this->user['usertokenkey']);
        return $this->success([
            'data' => [
                'token' => $this->user['usertokenkey'],
                'uid' => $uid,
                'WebSocket' => env('websocket_url', '')
            ]
        ]);
    }
}