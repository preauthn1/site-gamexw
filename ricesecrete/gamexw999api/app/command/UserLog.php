<?php

namespace app\command;

use app\api\factory\GameFactory;
use app\common\model\GameSetModel;
use app\common\model\UserLogModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class UserLog extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('handle log')
            ->setDescription('Handle user log');//处理用户日志
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        //处理日志
//        $this->handleLog();

        //处理在线时长
//        $this->handleOnlineTime();

    }

    //处理日志
    private function handleLog(){
        $userLogListLogKey = 'user_log_list';#用户日志列表缓存key
        $saveAll = [];
        $successNum = 0;
        while (count(Cache::store('redis')->lrange($userLogListLogKey, 0, -1)) > 0){
            //取出日志数据
            $data = json_decode(Cache::store('redis')->lpop($userLogListLogKey), true);
            //单独处理游戏时间数据
            if( $data['type'] == UserLogModel::GAME_TIME_TYPE ){
                //处理游戏日志日志时间戳
                $info['datetime'] = strtotime(date('Y-m-d 00:00:00', $data['datetime']));
                //判断数据是否重复
                $_isAdd = false;
                foreach ($saveAll as $k => $v){
                    if( $v['uid'] == $data['uid'] && $v['datetime'] == $data['datetime'] && $v['type'] == $data['type'] ){
                        $saveAll[$k]['data'] = $v['data'] + $data['data'];
                        $_isAdd = true;
                        break;
                    }
                }
                if( $_isAdd ){
                    continue;
                }
                //判断该类型数据是否存在
                $condition = [];
                $condition[] = ['type', '=', $data['type']];
                $condition[] = ['uid', '=', $data['uid']];
                $condition[] = ['datetime', '=', $data['datetime']];
                $userLogDetail = UserLogModel::where($condition)->find();
                if( $userLogDetail ){
                    //存在修改原纪录时长
                    $data['id'] = $userLogDetail['id'];
                    $data['data'] = $data['data'] + $userLogDetail['data'];
                }
                $saveAll[] = $data;
            }else{
                //不是游戏数据直接存
                $saveAll[] = $data;
            }
            $successNum++;
        }
        //保存用户日志中
        $res = (new UserLogModel())->saveAll($saveAll);
        if( !$res ){
            //不成功重新加入缓存中，下次执行
            foreach ($saveAll as $v){
                Cache::store('redis')->rpush($userLogListLogKey, json_encode($v));
            }
            $successNum = 0;
        }
        $this->log('log success num: '.$successNum);
    }

    //处理在线时长
    private function handleOnlineTime(){
        $userTimeLogListKey = 'today_login_time_list';#用户时长日志列表缓存key

        //批量保存数组
        $allArr = [];
        $successNum = 0;
        while (count(Cache::store('redis')->lrange($userTimeLogListKey, 0, -1)) > 0){
            $data = json_decode(Cache::store('redis')->lpop($userTimeLogListKey), true);
            //判断数据是否重复
            $_isAdd = false;
            foreach ($allArr as $k => $v){
                if( $v['uid'] == $data['uid'] && $v['datetime'] == $data['datetime'] && $v['type'] == $data['type'] ){
                    $allArr[$k]['data'] = $v['data'] + $data['data'];
                    $_isAdd = true;
                    break;
                }
            }
            if( $_isAdd ){
                continue;
            }
            //判断今日用户是否添加数据
            $condition = [];
            $condition[] = ['datetime', '=', strtotime('today')];
            $condition[] = ['uid', '=', $data['uid']];
            $condition[] = ['type', '=', UserLogModel::ONLINE_TIME_TYPE];
            $userLogDetail = UserLogModel::where($condition)->find();
            if( $userLogDetail ){
                $data['id'] = $userLogDetail['id'];
                $data['data'] += $userLogDetail['data'];
            }
            $allArr[] = $data;
            $successNum++;
        }
        //保存用户日志中
        $res = (new UserLogModel())->saveAll($allArr);
        if( !$res ){
            //不成功重新加入缓存中，下次执行
            foreach ($allArr as $v){
                Cache::store('redis')->rpush($userTimeLogListKey, json_encode($v));
            }
            $successNum = 0;
        }
        $this->log('online time success num: '.$successNum);
    }

    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}