<?php
namespace app\manage\factory;
use app\common\Utils;
use think\facade\Request;
use app\common\lib\Respons;
use app\common\model\UserLevelDownModel;

class UserlevelDownFactory {

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
        $UserLevelDownModel =new UserLevelDownModel();
        $rsps['count']=$UserLevelDownModel->where($where)->count();
        $rsps['data']=$UserLevelDownModel
            ->alias('uld')
            ->join('user_level ul', 'ul.id=uld.level_id')
            ->where($where)->limit((($page-1)*$limit),$limit)->order("uld.id desc")->field("uld.*, ul.level_name")->select();
        $rsps['code']=1;
        $rsps['vip_upgrade'] = Utils::sysInfo('vip_upgrade');
        $rsps['vip_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        return Respons::run($rsps);

        // View::assign("vip_upgrade",$vip_upgrade);
        // return View::fetch('user_level/list');
    }
    
}
?>