<?php

namespace app\api\controller;

use app\api\redis\ApiRedis;
use app\common\model\AdvertisingModel;
use app\common\model\RocketActivityBoxLogModel;
use app\common\model\RocketActivityFuelLogModel;
use app\common\model\RocketActivityRecordModel;
use app\common\service\MessageService;
use think\facade\View;
use think\Request;

class Index
{

    //首页
    public function index(Request $request)
    {
        //火箭活动清除数据
        $uid = $request->param('uid');
        if (!empty($uid)) {
            RocketActivityBoxLogModel::where('uid', $uid)->delete();
            RocketActivityFuelLogModel::where('uid', $uid)->delete();
            $up_data = [
                'now_mileage' => 0,
                'now_level' => 1,
                'now_get_level' => 1,
            ];
            RocketActivityRecordModel::where('uid', $uid)->update($up_data);
            print_r("clear ok");
        }
        return 'app';
    }

    public function code()
    {
        $account = explode('/', (request()->param())['s'])['2'];
        $ApiRedis = new ApiRedis();
        $value = rand(123456, 999999);
        $ApiRedis->set($account . "_code", $value, 60);
        echo "<script>alert(" . $value . ")</script>";
        exit;
    }

    public function ok()
    {
        return json(['code' => 1, 'msg' => 'ok']);
    }

    public function msg(Request $request)
    {
        if ($request->isPost()) {
            $post = $request->param();
            if (!isset($post['msgtype']) || !in_array($post['msgtype'], ['pop', 'toast', 'recharge', 'withdraw', 'action'])) {
                return $this->error('消息类型有误');
            }
            if (empty($post['uid'])) {
                return $this->error('用户ID不能为空');
            }

            if (empty($post['content'])) {
                return $this->error('消息内容不能为空');
            }
            if (in_array($post['msgtype'], ['recharge', 'action']) && empty($post['amount'])) {
                return $this->error('请填写金额');
            }
            $title = empty($post['title']) ? '消息通知' : $post['title'];
            $time = empty($post['time']) ? 0 : $post['time'];
            $resend = $post['rest_time'];
            if ($post['uid'] === 'all') {
                MessageService::sendUserGroupMsg($post['content'], $post['msgtype'] == 'pop' ? 'pop' : 'toast', $time, $title,$resend);
            } elseif ($post['uid'] == 'admin') {
                $type = 1;
                if($post['msgtype'] == 'recharge'){
                    $type = 2;
                }else{
                    $type = 3;
                }
                MessageService::sendAdminGroupMsg($post['content'], $post['msgtype'] == 'pop' ? 'pop' : 'toast', $time, $title,$resend, $type);
            } else {
                if ($post['msgtype'] == 'recharge') {
                    MessageService::sendRechargeMsg($post['uid'], $post['amount']);
                } else if ($post['msgtype'] == 'action') {
                    MessageService::sendJsonMsg([
                        'msg' => $post['content'],
                        'amount' => $post['amount']
                    ], $post['uid'], 'activity', $time, $title,$resend);
                } else if ($post['msgtype'] == 'withdraw') {
                    MessageService::sendWithdrawMsg($post['uid'], $post['amount'], 'withdraw', '', $resend);
                } else {
                    MessageService::sendMsg($post['content'], $post['uid'], $post['msgtype'], $time, $title,$resend);
                }
            }
            return $this->success($post);
        }
        return View::fetch();
    }


    private function error($msg)
    {
        return json(['code' => 1, 'msg' => $msg]);
    }

    private function success($post, $msg = '提交成功')
    {
        return json(['code' => 1, 'msg' => $msg, 'data' => $post]);
    }

    public function show()
    {
        $data = request()->param('data');
        return json(json_decode(base64_decode($data)));
    }
}

