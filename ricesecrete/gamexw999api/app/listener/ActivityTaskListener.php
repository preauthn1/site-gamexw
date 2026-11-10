<?php

namespace app\listener;

use app\common\service\MemberService;
use think\facade\Queue;
use app\event\ActivityTaskEvent;
class ActivityTaskListener {

//    'action_base_type' => [
//        1  => '注册首存',
//        2  => '再存优惠',
//        3  => '一般优惠',
//        4  => '体验金',
//        5  => '展示活动',
//    ],
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle(ActivityTaskEvent $event) {
        $params = $event->params;
        if (!isset($params['type']) || !isset($params['uid'])) {
            return;
        }

        // 判断是否禁止参与活动
        if (MemberService::tagBan($params['uid'], 'is_activity')) {
            return;
        }

        if (!is_array($params['type'])) {
            $params['type'] = [$params['type']];
        }

        $types = [1, 2, 3, 6,7];
        foreach ($params['type'] as $type) {
            if (!in_array($type, $types)) {
                continue;
            }

            Queue::later($params['time'] ?? 3, "ActivityTask@type{$type}", $params, 'activity');
        }
    }
}