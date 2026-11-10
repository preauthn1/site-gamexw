<?php

namespace app\job;

use app\common\lib\EmailCode;
use app\common\model\AdminLogModel;
use app\common\model\UserMsgModel;
use think\queue\Job;
use think\facade\Cache;

class SendMsg {

    public function fire(Job $job, $data) {
        
    }

    /**
     * UserMsg 发送站内信
     * @param Job $job
     * @param type $data
     */
    public function usermsg(Job $job, $data) {
        if (!isset($data['lang']) || !isset($data['add_time']) || !isset($data['uid']) || !isset($data['title']) || !isset($data['content']) || !isset($data['type']) || !isset($data['send_num']) || !isset($data['current']) || !isset($data['admin_name'])) {
            $this->output('参数有误');
            $this->output(json_encode($data));
            $job->delete();
            return;
        }
        if (!is_numeric($data['uid'])) {
            $job->delete();
            return;
        }
        $redis = Cache::store('redis');
        if($data['uid'] > 0) {
            $redis_key = "import_handle:usermsg:{$data['uid']}";
            if ($redis->has($redis_key)) {
                $this->output("1800秒内重复发送[{$data['uid']}]");
                $job->delete();
                return;
            }
            $redis->set($redis_key, 1, 1800);
        }
        $this->output("用户[{$data['uid']}]，标题[{$data['title']}]，语种:[{$data['lang']}]]");

        $add = [
            'uid'       => $data['uid'],
            'lang'      => $data['lang'],
            'add_time'  => $data['add_time'],
            'title'     => $data['title'],
            'content'   => $data['content'],
            'type'      => $data['type'],
        ];
        $info = UserMsgModel::create($add);
        if (!$info) {
            $this->output("发送失败[{$data['uid']}]");
            $job->delete();
            return;
        }
     
        $rear_data = [
            'id'        => $info->id,
            'uid'       => $data['uid'],
            'lang'      => $data['lang'],
            'add_time'  => $data['add_time'],
            'title'     => $data['title'],
            'content'   => $data['content'],
            'type'      => $data['type'],
        ];
        if ($data['current'] + 1  == $data['send_num']) {
            $log = updata(new UserMsgModel(), [], $rear_data);  //日志数据
            $handle_name = '站内信发送';
            $this->addLog($data['send_num'], $log['rear_data'], $log['table'], $handle_name, $data['admin_name']); //记录日志
        }
        $job->delete();
    }

    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }

    //添加日志
    public function addLog($send_num = '',$rear_data = '',$table = '',$handle_name = '', $admin_name = '') {
        $add = [];
        $add['add_time'] = time();
        $add['admin_name'] = $admin_name;
        $add['log_url'] = 'queue';
        $add['handle_name'] = $handle_name;
        $add['admin_id'] = 0;
        $add['admin_account'] = $admin_name;
        $add['forward_data'] = '发送人数'.$send_num;
        $add['rear_data'] = $rear_data;
        $add['table'] = $table;
        AdminLogModel::create($add);
    }


    public function play_email(Job $job, $data){
        $redis = Cache::store('redis');
        $email = $data['email'];
//        foreach($email_arr as $k => $v){
            $redis_key = "import_handle:play_email:{$email}_{$data['title']}";
            if ($redis->has($redis_key)) {
                $this->output("600秒内重复发送[{$email}]");
                $job->delete();
                return;
            }
            $redis->set($redis_key, 1, 60);
//        }
        $other = [];
        $other['title'] = $data['title'];
        $other['content'] = $data['content'];
        $this->output("邮件模板：{$data['title']}");
        EmailCode::notifyMail($email, $other);
        $job->delete();
    }
}
