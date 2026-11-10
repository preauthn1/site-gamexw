<?php

declare (strict_types=1);

namespace app\promote\middleware;

use app\common\lib\Respons;
use thans\jwt\middleware\BaseMiddleware;

class Auth extends BaseMiddleware
{

    protected $header = [
        'Access-Control-Expose-Headers' => 'Authorization',
    ];

    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {
        // OPTIONS请求直接返回
        if ($request->isOptions()) {
            exit();
        }
        $newToken = null;
        $ttl = config("jwt.ttl");
        // 验证token
        try {
            $payload = $this->auth->auth();
            $exp = $payload['exp']->getValue();
            if ($exp - time() < $ttl / 5) {
                $newToken = $this->auth->builder(['client_user_id' => $payload['client_user_id']]);
            }
        } catch (\Exception $e) {
            return Respons::run(401, [], $e->getMessage());
        }
        $request->auth_middleware = true;
        $response = $next($request);
        if ($newToken) {
            return $this->setAuthentication($response, $newToken)->header($this->header);
        }

        return $response;
    }

}
