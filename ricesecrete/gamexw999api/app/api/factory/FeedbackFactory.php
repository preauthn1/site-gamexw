<?php
namespace app\api\factory;
use app\api\redis\ApiRedis;
use think\facade\Request;
use think\facade\Db;
use app\common\model\FeedbackModel;

class FeedbackFactory
{
    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->param=Request::param();
        $this->uid=$uid;
    }

    //提交反馈
    public function set_run()
    {
        $content = $this->param['content'];
        if(empty($content)){
            ajax_return("the content can not be blank", 0);
        }

        $ApiRedis=new ApiRedis();
        //锁
        if (!$ApiRedis->lock("Feedback_{$this->uid}")) {
            ajax_return("Do not visit frequently", 0);
        }

        Db::startTrans();
        try {
            $da = [
                'uid'=>$this->uid,
                'content'=>$content,
                'add_time'=>time(),
            ];
           $res = FeedbackModel::insertGetId($da);
           if(!$res){
               throw new \Exception("operation failed", 400);
           }
            Db::commit();
            $rsps['code']=1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code']=0;
            $rsps['msg']=$e->getMessage();
        }
        return $rsps;
    }

}