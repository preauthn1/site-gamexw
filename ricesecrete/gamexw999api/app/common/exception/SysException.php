<?php

namespace app\common\exception;

use app\ExceptionHandle;
use think\Response;
use app\common\lib\Respons as myRespons;
use Throwable;
use app\common\exception\MessageException;

class SysException extends ExceptionHandle {

    public function render($request, Throwable $e): Response {
        if ($e instanceof MessageException) {
            return myRespons::run($e->getCode(), [], $e->getMessage());
        }
        // 其他错误交给系统处理
        return parent::render($request, $e);
    }

}
