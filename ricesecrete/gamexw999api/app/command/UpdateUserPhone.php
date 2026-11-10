<?php

namespace app\command;

use app\api\redis\ApiRedis;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Cache;
use think\facade\Db;

class UpdateUserPhone extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('update:userphone')
            ->addArgument("day", Argument::OPTIONAL, 'before day')
            ->addArgument("type", Argument::OPTIONAL, '0 all, 1 phone, 2email')
            ->setDescription('update user phone before day');//修改多少天前未充值的用户号码/邮箱
    }

    // TODO 该任务执行时间必须小于20分钟，根据缓存id时间定
    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        $day = $input->getArgument('day') ?: 365;
        $type = $input->getArgument('type');#0 all, 1 phone, 2email
        $this->updateUserPhoneOrEmail($day, $type);
        $this->log();
    }

    //修改指定天数前未充值的用户号码或邮箱 TODO 该任务执行时间必须小于20分钟，根据缓存id时间定
    public function updateUserPhoneOrEmail($day = 365, $type = 0){
        $time = time() - (86400 * $day);
        $limit = env('update_user_phone_before', 5000);
        $id = 0;
        $apiRedis = new ApiRedis();
        $redisKey = 'update_user_phone_before_key';
        if( $apiRedis->has($redisKey) ){
            $id = $apiRedis->get($redisKey);
        }
        $condition = [];
        $condition[] = ['a.id', '>', $id];
        $condition[] = ['a.add_time', '<=', $time];
        $condition[] = ['a.phone|a.mail', 'not like', '%#%'];
        $userList = UserModel::alias('a')
            ->join('user_offline b', 'b.uid=a.id and b.status in (3,4)', 'left')
            ->where($condition)
            ->field('a.id,a.phone,a.mail,count(b.id) as recharge_num')
            ->group('a.id')
            ->having('count(b.id) < 1')
            ->order('a.id asc')
            ->limit($limit)
            ->select();
        $uidArr = [];
        switch ($type){
            case 0:
                //邮箱和号码都更改
                $uSql = "phone = CONCAT(phone, '#'),mail = CONCAT(mail, '#')";
                break;
            case 1:
                //只更改号码
                $uSql = "phone = CONCAT(phone, '#')";
                break;
            case 2:
                //只更改邮箱
                $uSql = "mail = CONCAT(mail, '#')";
                break;
            default:
                $this->log('type error');exit;
        }
        foreach ($userList as $v){
            //验证是否充值过
//            if ( UserOfflineModel::where('uid', '=', $v->id)->where('status', 'in', '3,4')->value('id') ){
//                dd($v->id.'已充值');
//            }
            $uidArr[] = $v->id;
            //修改对应数据
            $sql = 'UPDATE dq_user SET '.$uSql.' where id = ' . $v->id .';';
            Db::query($sql);
            $id = $v->id;
        }
        //验证没有在uidArr中的用户是否都充值过
//        $_userList = UserModel::where('id', 'not in', $uidArr)->order('id asc')->select();
//        foreach ($_userList as $v){
//            //判断
//            if ( !UserOfflineModel::where('uid', '=', $v->id)->where('status', 'in', '3,4')->value('id') ){
//                dd($v->id.'没有充值');
//            }
//        }
        if( count($userList) < 1 ){
            $id = 0;
            $this->log('all user updated');
        }
        //设置下次id开始缓存
        $apiRedis->set($redisKey, $id, 60 * 20);
    }

    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}