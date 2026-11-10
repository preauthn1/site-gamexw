<?php
namespace app\api\middleware;
use app\common\model\GameOperationModel;
use app\common\model\GameSetModel;
use app\common\model\GameUserModel;
use think\facade\Request;
use think\facade\Log;
class SaBa
{
    /**
     * @param $codes 自定义返回code
     */
    public function handle($request, \Closure $next)
    {
        $game = GameSetModel::get_config('SaBa');
        $status = $game['game_status'];
        if ($status!=1){
            $rsps['status'] = 501;
            $rsps['msg'] = "game not exist";
            return json($rsps);
        }
        if (!isset($game['id'])) {
            $rsps['status'] = -1;
            $rsps['msg'] = "game not exist";
            return json($rsps);
        }
        $config = $game['config'];
        //更新余额
        $content = file_get_contents('php://input');
        if(isset($_GET['sd'])){
            $params = json_decode($content,true);
        }else{
            $params = json_decode(gzdecode($content),true);
        }


        $key = $params['key'];
        if (empty($key) || $config['vendor_id']!=$key){
            $rsps['status'] = -1;
            $rsps['msg'] = "key error";
            return json($rsps);
        }
        $message=$params['message'];
        Log::write(['Saba_arr',$message['action'],json_encode($params)], "payment");
        if (empty($message)){
            $rsps['status'] = -1;
            $rsps['msg'] = "message is none";
            return json($rsps);
        }
        $action = $message['action']??'';
        if (empty($action)){
            $rsps['status'] = -1;
            $rsps['msg'] = "action is none";
            return json($rsps);
        }
        $userId = $message['userId']??'';
        if (!empty($userId)){
            $GameUser = GameUserModel::where('member_id',$userId)->where("game_id",$game['id'])->find();
            if (!isset($GameUser['id'])){
                $rsps['status']=203;
                $rsps['msg']='user not exist';
                return json($rsps);
            }
            $request->uid=$GameUser['uid'];
        }
        $operationId = $message['operationId']??'';
        if (!empty($operationId)){
            $GameOperationModel = new GameOperationModel();
            $operation = [];
            $operation['game_id']=$game['id'];
            $operation['operation_id']=$operationId;
            $operation['action']=$action;
            $operation['message']=json_encode($message);
            $operation['add_time']=time();
            $GameOperationModel->insert($operation);
        }
        $request->gid=$game['id'];
        $request->action=$action;
        $request->package='SaBa';
        $request->info=$message;
        return $next($request);
    }
}