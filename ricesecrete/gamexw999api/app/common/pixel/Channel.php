<?php

namespace app\common\pixel;


abstract class Channel {


    // 事件名称映射数组
    protected $events = [];
    protected $token = null;
    protected $code = null;
    protected $config = [];

    /**
     * 像素配置
     * @param $config
     */
    public function __construct($config) {
        $this->init($config);
    }

    protected function init($config) {}

    /**
     * @param string $eventName
     * @param array $customData
     * @param string $eventId
     * @param array|null $userData
     * @return void
     */
    public function send(string $eventName, array $customData, string $eventId, array $userData = null) {}

    protected function getEventName($event) {
        return $this->events[$event] ?? $event;
    }
}
