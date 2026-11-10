<?php

namespace app\job;

use app\common\model\UserModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserAdminLogModel;
use app\common\model\AdminLogModel;
use app\common\service\MemberService;
use think\facade\Cache;
use think\queue\Job;

class ImportHandle {

    public function fire(Job $job, $data) {
        
    }

    /**
     * ArtificialBalance 批量手动上分
     * @param Job $job
     * @param type $data
     */
    public function artificial(Job $job, $data) {
        if (!isset($data['username']) || !isset($data['price']) || !isset($data['water']) || !isset($data['a_type'])) {
            $this->output('参数有误');
            $this->output(json_encode($data));
            $job->delete();
            return;
        }

        $user = UserModel::where("username", $data['username'])->find();
        $admin_name = isset($data['admin_name']) ? $data['admin_name'] : '';
        if (empty($user)) {
            $this->output('用户不存在' . $data['username']);
            $job->delete();
            return;
        }
        if ($data['price'] <= 0) {
            $this->output("[{$data['username']}]上分金额小于0：" . $data['price']);
            $job->delete();
            return;
        }

        $redis = Cache::store('redis');
        $redis_key = "import_handle:artificial:{$user['id']}:{$data['a_type']}";
        if ($redis->has($redis_key)) {
            $this->output("3600秒内重复上分[{$data['username']}]");
            $job->delete();
            return;
        }
        $redis->set($redis_key, 1, 3600);
        $this->output("用户[{$user['id']}]，用户[{$data['username']}]，上分金额:[{$data['price']}]，上分类型:[{$data['a_type']}]，流水倍数：[{$data['water']}]");

        $msg = '无';
        if (!isset($data['msg'])) {
            if ($data['a_type'] == 1) {
                $msg = "余额增加";
                $mx = UserModel::cz_balance($user['id'], $data['price'], "+", true, "余额增加", 3);
            } elseif ($data['a_type'] == 2) {
                $msg = "余额减少";
                $mx = UserModel::cz_balance($user['id'], $data['price'], "-", true, "余额减少", 4);
            } elseif ($data['a_type'] == 3) {
                $msg = "赠送彩金";
                $mx = UserModel::cz_balance($user['id'], $data['price'], "+", true, "赠送彩金", 12);
            }
        } else {
            $msg = $data['msg'];
            switch ($data['a_type']) {
                case 1:
                    $user_model_type = 3;
                    break;
                case 2:
                    $user_model_type = 4;
                    break;
                case 3:
                    $user_model_type = 12;
                    break;
            }
            $mx = UserModel::cz_balance($user['id'], $data['price'], $data['a_type'] == 2 ? '-' : '+', true, $data['msg'], $user_model_type);
        }
        if (empty($data['a_type'])) {
            $data['a_type'] = 1;
        }

        $add['admin_name'] = $admin_name;
        $add['a_type'] = $data['a_type'];
        $add['uid'] = $user['id'];
        $add['mx_id'] = $mx['id'];
        $add['price'] = $data['price'];
        $add['water'] = $data['water'];
        $add['add_time'] = time();
        $add['lebal'] = $user['lebal'];
        $add['msg'] = $msg;
        $info = ArtificialBalanceModel::create($add);
        if (!$info) {
            $this->output("上分失败[{$data['username']}]");
            $job->delete();
            return;
        }
        $logMsg = '';
        if ($add['a_type'] == 1 || $add['a_type'] == 3) {
            $fee = sprintf("%.2f", $add['price'] * $add['water']);
            UserModel::where(['id' => $user['id']])->inc("savings", $fee)->update();  //储蓄字段 充值增加
            MemberService::addUserWaterDetail($user['id'], $fee, 10, $msg, 0, $add['price'], $add['water']);
            $logMsg = ',流水: '.$fee;
        }
        UserAdminLogModel::add_log($user['id'], "金额调整导入, 类型: {$msg}, 金额: {$data['price']}" . $logMsg, $admin_name);
        $rear_data = [
            'id' => $info->id,
            'uid' => $user['id'],
            'a_type' => $data['a_type'],
            'price' => $data['price'],
            'water' => $data['water'],
            'lebal' => $user['lebal'],
        ];
        $forward_data = [];
        $log = updata(new ArtificialBalanceModel(), $forward_data, $rear_data);  //日志数据
        $handle_name = '人工调整导入';
        $this->addLog($log['forward_data'], $log['rear_data'], $log['table'], $handle_name, $admin_name); //记录日志
        $job->delete();
    }

    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }

    //添加日志
    public function addLog($forward_data = '',$rear_data = '',$table = '',$handle_name = '', $admin_name = '') {
        $add = [];
        $add['add_time'] = time();
        $add['admin_name'] = $admin_name;
        $add['log_url'] = 'queue';
        $add['handle_name'] = $handle_name;
        $add['admin_id'] = 0;
        $add['admin_account'] = $admin_name;
        $add['forward_data'] = $forward_data;
        $add['rear_data'] = $rear_data;
        $add['table'] = $table;
        AdminLogModel::create($add);
    }
}
