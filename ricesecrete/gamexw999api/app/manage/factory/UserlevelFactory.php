<?php
namespace app\manage\factory;
use app\common\Utils;
use think\facade\Request;
use app\common\lib\Respons;
use app\common\model\UserLevelModel;

class UserlevelFactory {

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list() {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $UserLevelModel =new UserLevelModel();
        $rsps['count']=$UserLevelModel->where($where)->count();
        $rsps['data']=$UserLevelModel->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
        $rsps['code']=1;
        $rsps['vip_upgrade'] = Utils::sysInfo('vip_upgrade');
        $rsps['vip_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        return Respons::run($rsps);
        // View::assign("vip_upgrade",$vip_upgrade);
        // return View::fetch('user_level/list');
    }
    
}
?>