<?php
namespace app\admin\controller;
use app\common\lib\Respons;
use app\common\model\UserModel;
use think\facade\Request;
class Recursion
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //图片上传
    public function index(){
        $user = UserModel::where([])->order("id asc")->select();
        foreach($user as $key => $val){
            $tree = $this->test($val['rec_id']);
            $tree[] = $val['id'];
            $tree = implode("/", $tree);
            echo "/".$tree."/"."<br>";
            UserModel::where(["id" => $val['id']])->save(["tree" => "/".$tree."/"]);
        }
        //return Respons::run($rsps);
    }
    
    public function level(){
        $user = UserModel::where([])->order("id asc")->select();
        foreach($user as $key => $val){
            $tree = explode("/", $val['tree']);
            echo count($tree);
            $new_tree = array_filter($tree);
            echo count($tree) ."---".count($new_tree)."<br>";
            UserModel::where(["id" => $val['id']])->save(["rec_level" => count($new_tree)]);
        }
        //return Respons::run($rsps);
    }
    
    public function test($id=0, $result=''){
        $arr = [];
        $user = UserModel::where(['id' => $id])->find();
        if ($user['rec_id'] > 0) {
            $arr[] = $user['id'];
            $arr = array_merge($this->test($user['rec_id']),$arr);
        }else{
            if(isset($user['id'])){
                $arr[] = $user['id'];
            }
        }
        return $arr;
    }
}