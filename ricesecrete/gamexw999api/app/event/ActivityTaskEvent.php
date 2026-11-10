<?php

namespace app\event;

class ActivityTaskEvent {

    public $params;
    public function __construct($params) {
        $this->params = $params;
    }
}