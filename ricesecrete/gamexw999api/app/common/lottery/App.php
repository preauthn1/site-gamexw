<?php
namespace app\common\lottery;

use app\common\lottery\interfaces\Type;

class App {

    /**
     * @param $type
     * @param $lottery
     * @return Type
     */
    public static function init($type, $lottery) {
        $lottery_class = "app\\common\\lottery\\type\\" . ucfirst($type);
        if (class_exists($lottery_class)) {
            return new $lottery_class($lottery);
        } else {
            throw new \Exception('Creating Lottery Object fail');
        }
    }
}