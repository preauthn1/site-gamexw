<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\AdminModel;
use app\common\model\AdminRoleModel;
use think\facade\Request;
use think\facade\Session;

class AdminFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function lists()
    {
        
        $AdminModel = new AdminModel();
        $param=Request::param();
        $admin = Session::get("adminInfo");
        [$admin_id,$role_id] = [
            Session::get("adminInfo.id"),
            Session::get("adminInfo.role_id")
        ];
        $AdminRoleModel = new AdminRoleModel();
        $admin_role_data = $AdminRoleModel
                ->when($admin_id > 1, function($query) use($admin_id){
                    $query->where([['id', ">", 1]]);
                })->select();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $name = $param['name']??"";
        $where = [];
        if($name){
            $where[] = ["username", "like", "%".$name."%"];
        }
        $data = $AdminModel
            //当管理员id 大于1 的时候 否则就是超级管理员 直接获取全部
            ->when($admin_id > 1, function($query) use($admin_id){
                $query->where('parent_id', $admin_id)->whereOr('id', $admin_id);
            })
            ->where($where)
            ->with('adminRole')
            ->paginate($limit,false,['query' => $param])
            //动态添加属性
            ->bindAttr('adminRole', ['role_name' => 'role_name'])
            ->hidden(['adminRole']);
        $code = 1;
        return Respons::run(compact('code', 'data', 'admin_role_data'));
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
}