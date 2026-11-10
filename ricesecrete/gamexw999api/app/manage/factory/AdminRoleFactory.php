<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AdminRoleModel;
use think\facade\Request;
use think\facade\Session;

class AdminRoleFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0, AdminRoleModel $AdminRoleModel){
        $this->uid=$uid;
        $this->AdminRoleModel = $AdminRoleModel;
    }

    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'] ?? 10;
        [$admin_id, $role_id] = [
            Session::get("adminInfo.id"),
            Session::get("adminInfo.role_id")
        ];

        $data = $this->AdminRoleModel
            ->when($role_id > 1, function($query) use($admin_id){
                $query->where(['admin_id' => $admin_id]);
            })
            ->paginate($limit, false, ['query' => $param]);
        $code = 1;
        return Respons::run(compact('code', 'data'));
    }
}