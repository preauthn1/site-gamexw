<?php
namespace app\api\factory;
use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\model\ServiceModel;
use app\common\model\UserModel;
use app\common\lib\Util;
use think\facade\Request;

class ServiceFactory
{
    private $param;//请求参数
    private $uid = 0;
    //验证
    public function __construct($uid=0){
        $this->param=Request::param();
    }

    //公告列表
    public function list_run(){
        $pagenow=$this->param['page']??1;
        $limit=$this->param['limit']??10;

        //如果登录就获取用户uid
        if( !$this->uid ){
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if( $token ){
                    $this->uid = $token->uid;
                }
            }catch (\Exception $e){}
        }
        $whereStr = 'agent_id=0';
        if( $this->uid ){
            $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id")->find();
            if(isset($uinfo['tree'])){
                $tree1 = explode("/", $uinfo['tree']);
                $whereStr = "agent_id=0 or agent_id=".$tree1[1];
            }

        }

        $field = 'name,introduce,link,img';
        $data=ServiceModel::where("status",1)
            ->where($whereStr)->field($field)
            ->limit((($pagenow-1)*$limit),$limit)
            ->order('sort desc')
            ->select();

        //用户登录情况2小时换一次
        $apiRedis=new ApiRedis();
        //如果登录就获取用户uid
        $uid = 0;
        $header = request()->header();
        try {
            $token = TokenCheck::run($header);
            if( $token ){
                $uid = $token->uid;
            }
        }catch (\Exception $e){}
        if( $uid ){
            $changeServiceUrlKey = 'change_service_url1_'.$uid;
            if( $apiRedis->has($changeServiceUrlKey) ){
                $data = json_decode($apiRedis->get($changeServiceUrlKey), true);
            }else {
                foreach ($data as $key => $value) {
                    //将链接转成数组随机取出一个
                    $data[$key]['link'] = handleManyUrl( $value['link']);
                }
                //设置两小时缓存
                $apiRedis->set($changeServiceUrlKey, json_encode($data), 60*60*2);
            }
        }else{
            foreach ($data as $key => $value) {
                //将链接转成数组随机取出一个
                $data[$key]['link'] = handleManyUrl( $value['link']);
            }
        }


        $rsps['data']=$data;
        $rsps['code']=1;
        return $rsps;
    }

}