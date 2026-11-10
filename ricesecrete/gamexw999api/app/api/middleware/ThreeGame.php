<?php
namespace app\api\middleware;

use app\common\model\{GameOperationModel, GameSetModel, GameUserModel};
use think\facade\{Request, Log};

class ThreeGame
{

    /**
     * 给到游戏方 给小写的  回调链接的 type标识 => 系统所使用的标识
     * @param $key
     * @return string
     */
    private function sys_sign_type($key){
        $arr = [
            'bg' => 'BG',
            'rocket' => 'Rocket',
        ];
        return $arr[$key] ?? '';
    }

    /**
     * 统一入口
     * @param String $key
     * @param String $action
     * @return string
     */
    private function sys_sign_action(String $key, String $action){
        //键是 游戏方的操作名  值是我方系统 三方游戏文件的游戏名
        $arr = [
            'BG' => [
                'play' => 'play_game',
                'rollback' => 'rollback',
            ],
            'Rocket' => [
                'getBalance' => 'getbalance',
                'bet' => 'bet',
                'unbet' => 'unbet',
                'settle' => 'settle',
            ]
        ];
        return $arr[$key][$action] ?? '';
    }

    /**
     * 每个游戏的参数不一致 转换成后台需要的参数
     * @param String $key 游戏标识
     * @param String $par 用到的变量名
     * @return string
     */
    private function par_diff(String $key, String $par){
        $arr = [
            'BG' => [
                'game_code' => 'game',
                'uid' => 'user_id',
            ],
            'Rocket' => [
                'game_code' => 'channel',
                'uid' => 'username'
            ]
        ];
        return $arr[$key][$par] ?? '';
    }


    /**
     * 是否启用tp的参数过滤 不启用可能不安全 故针对某个平台使用 默认为 1
     * @param $key
     * @return string
     */
    private function param_filter($key){
        $arr = [
            'BG' => 0,
            'Rocket' => 1,
        ];
        return $arr[$key] ?? 1;
    }

    /**
     * @param $codes 自定义返回code
     */
    public function handle($request, \Closure $next)
    {
        $param = $request->param();
        $game_sign = $this->sys_sign_type($param['game_sys_sign']);
        if(!$game_sign){
            $rsps['status'] = 501;
            $rsps['msg'] = "game not exist";
            return json($rsps);
        }
        $wh['game_sys_sign'] = $game_sign;
        $game_code_par = $this->par_diff($game_sign, 'game_code');
        try {
            $wh['game_code'] = $param[$game_code_par];
        }catch(\Exception $E){
            return json("{$game_code_par} Not Null");
        }
        $game = GameSetModel::get_game($wh);
        $status = $game['game_status'] ?? '';
        if (!isset($game['id'])) {
            $rsps['status'] = -1;
            $rsps['msg'] = "game not exist";
            return json($rsps);
        }
        if ($status != 1){
            $rsps['status'] = 501;
            $rsps['msg'] = "game not exist";
            return json($rsps);
        }
        $uid_par = $this->par_diff($game_sign, 'uid');
        $request->uid = 0;
        if(isset($param[$uid_par])){
            $request->uid = GameUserModel::where(['member_id' => $param[$uid_par]])->value('uid');
        }
        $request->gid = $game['id'];
        $request->game=$game_sign;
        $request->action_method = $this->sys_sign_action($game_sign, $param['action_method']);
        $request->header = Request::header();
        if($this->param_filter($game_sign) == 0){
            $request->param = Request::param(false);
        }else{
            $request->param = Request::param();
        }
        return $next($request);
    }
}
