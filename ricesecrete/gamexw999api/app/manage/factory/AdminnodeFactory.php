<?php
namespace app\manage\factory;
use app\common\lib\Respons;
use think\facade\{Config, Request, View};
use app\common\model\{AdminModel, AdminNodeModel};
class AdminnodeFactory {

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function get_auth() {
        $admin_info = AdminModel::with('adminRole')->where('id', $this->uid)->find()->toArray();
        $list = AdminNodeModel::whereIn('id', $admin_info['adminRole']['role_permission'])->select()->toArray();
        $menu = [];
        foreach ($list as $key => $val) {
            if ($val['pid'] == 0) {
                $menu[] = [
                    'id' => $val['id'],
                    'path' => $val['node_url'],
                    'meta' => [
                        'title' => $val['node_name'],
                        'icon'  => $val['icon'],
                        'rank'  => $val['sort'],
                    ]
                ];
            }
        }
        foreach ($menu as $ke => &$va) {
            foreach ($list as $k => $v) {
                if ($va['id'] == $v['pid']) {
                    $va['children']['path'] = $v['node_url'];
                    $va['children']['name'] = $v['node_url'];
                    $va['children']['meta']['title'] = $v['node_name'];
                    $va['children']['meta']['roles'] = [];
                    $va['children']['meta']['auths'] = [];
                }
            }
        }
        return Respons::run(1,$menu);
    }

    //列表
    public function list(){
        $NodeShowArr = getNodeShow();
        $data['data'] = AdminNodeModel::with(['childrenOne'])
                        ->where(['not' => 0]) //权限规格的节点 不允许修改 不允许展示
                        ->order('sort ASC, id DESC')
                        ->withAttr('node_name',function($value){
                            return lang($value);
                        })
                        ->where('special_menu','in',$NodeShowArr)
                        ->select();
        //获取特殊权限的数组数据
        $data['special'] = Config::get('custom.special_menu');
        lang_change($data['special']);
        return Respons::run(1,$data);
    }
}
?>