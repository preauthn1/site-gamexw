<?php

namespace app\common\pixel;

use Exception;

class Pixel {

    /**
     * @param $type
     * @param $config
     * @return Channel
     * @throws Exception
     */
    public static function init($type, $config): Channel {
        $class = "app\\common\\pixel\\channel\\" . ucfirst($type);
        if (class_exists($class)) {
            return new $class($config);
        } else {
            throw new Exception('Creating Pixel Object fail');
        }
    }
}