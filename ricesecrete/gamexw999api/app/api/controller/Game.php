<?php
namespace app\api\controller;

use app\api\factory\GameFactory;
use app\common\FastGame\Base\Utils;
use app\common\lib\Respons;
use app\common\lib\Util;
use think\Request;
use app\common\FastGame\Base\BaseController;

class Game extends BaseController
{
    public function index(Request $request){
        $rsps=(new GameFactory())->index_run($request);
        return json($rsps);
    }

    public function index1(Request $request){
        $rsps=(new GameFactory())->index1_run($request);
        return json($rsps);
    }
    public function index2(Request $request){
        $rsps=(new GameFactory())->index2_run();
        return json($rsps);
    }

    public function saba(Request $request, $action){
        $rsps=(new GameFactory())->$action($request);
        return json($rsps);
    }

    public function get_game_type(Request $request){
        $rsps=(new GameFactory())->get_game_type();
        return json($rsps);
    }

    public function get_game_third(Request $request){
        $rsps=(new GameFactory())->get_game_third();
        return json($rsps);
    }

    public function get_game_url(Request $request){
        $rsps=(new GameFactory())->get_game_url($request);
        return json($rsps);
    }

    public function platform_balance(Request $request){
        $rsps=(new GameFactory($request->token->uid))->get_platform_balance();
        return json($rsps);
    }


    /**
     * 统计报表
     * @param Request $request
     */
    public function get_game_report(Request $request){
        $rsps = (new GameFactory())->game_report();
        return json($rsps);
    }

    /**
     * 游戏列表
     * @param Request $request
     */
    public function game_list(Request $request){
        $rsps = (new GameFactory())->game_list();
        return json($rsps);
    }

    /**
     * 游戏详情
     * @param Request $request
     */
    public function game_detail(Request $request){
        $rsps = (new GameFactory($request->token && $request->token->uid ? $request->token->uid : 0))->game_detail();
        return json($rsps);
    }

    /**
     * 游戏订单列表
     * @param Request $request
     */
    public function game_order(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_order();
        return json($rsps);
    }

    /**
     * 游戏订单列表（旧）
     * @param Request $request
     */
    public function game_order1(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_order1();
        return json($rsps);
    }

    /**
     * 体育订单列表（旧）
     * @param Request $request
     */
    public function game_order2(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_order2();
        return json($rsps);
    }

    public function game_order3(Request $request){
        $rsps = (new GameFactory())->game_order3($request->param());
        return json($rsps);
    }

    /**
     * 获取最高下注和最大盈利订单
     * @param Request $request
     * @return \think\response\Json
     */
    public function game_order4(Request $request){
        $rsps = (new GameFactory())->game_order4($request->param());
        return json($rsps);
    }

    /**
     * 游戏详情
     * @param Request $request
     */
    public function game_order_detail(Request $request)
    {
        $rsps = (new GameFactory($request->token->uid))->game_order_detail();
        return json($rsps);
    }
    /**
     * 三方共享钱包 统一入口
     * @param Request $request
     * @return \think\response\Json
     */
    public function play_game(Request $request){
        if ($request->game=='BG' && in_array($request->param('user_id'),['82224234','26644244'])){
            //TWT test
            Utils::L('test',[['param'=>$request->param,'header'=>$request->header],$request->param('action_method')]);
            $rlt= \app\common\FastGame\Base\Notify::run(4,['param'=>$request->param,'header'=>$request->header],$request->param('action_method'));
            Utils::L('test',['rlt'=>$rlt]);
            return $rlt;
        }else {
            $rsps = (new GameFactory())->get_game_common_action($request);
            return $rsps;
        }
    }


    /**
     * 单一钱包回调处理
     */
    public function third_notify(Request $request){
        Utils::L("{$request->tc_id}/notify",['param'=>$request->param,'header'=>$request->header], true);
        try {
            $rlt = \app\common\FastGame\Base\Notify::run($request->tc_id,['param'=>$request->param,'header'=>$request->header],$request->param('action_method'));
        } catch (\Exception $e) {
            Utils::L("{$request->tc_id}/notify_error",[
                'url' => $request->url(true),
                'ip' => Util::get_ip(),
                'param'=>$request->param,
                'header'=>$request->header,
                'message'=>$e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], true, true);
            $rlt = [
                'error' => $e->getMessage()
            ];
        }
        //添加header
        $header = [];
        if( isset($rlt['header']) && $rlt['header'] ){
            $header = $rlt['header'];
            unset($rlt['header']);
        }
        $httpcode = 200;
        if( isset($rlt['httpcode']) && $rlt['httpcode'] ){
            $httpcode = $rlt['httpcode'];
            unset($rlt['httpcode']);
        }
        Utils::L("{$request->tc_id}/notify", ['response' => $rlt], true);
        //如果是xml
        if(isset($rlt['type']) && $rlt['type'] == 'xml'){
            return response($rlt['xml'], $httpcode, ['Content-Type' => 'application/xml']);
        }
        return json($rlt, $httpcode, $header);
    }


    //登录
    public function login_threee_game(Request $request){
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $rsps = (new GameFactory($request->token->uid))->login_threee_game();
        return json($rsps);
    }
    //登录lucky soprt
    public function login_luckysport_game(Request $request){
        header('Cache-Control: no-cache, no-store, must-revalidate');
        $rsps = (new GameFactory($request->token->uid))->login_luckysport_game();
        return json($rsps);
    }

    //获取余额
    public function get_user_third_balance(Request $request){
        return (new GameFactory($request->token->uid))->get_user_third_balance();
    }

    //余额转回
    public function user_third_transout(Request $request){
        return (new GameFactory($request->token->uid))->user_third_transout();
    }

    //获取记录
    public function get_game_logs(){
        return (new GameFactory())->get_game_logs();
    }

    //游戏统计
    public function game_statistics(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_statistics();
        return json($rsps);
    }

    //fb未登录的时候 获取登录token
    public function no_login_threee_game(Request $request){
        $rsps = (new GameFactory())->fb_no_login_threee_game();
        return json($rsps);
    }

    //返佣记录
    public function agent_report(Request $request){
        $rsps = (new GameFactory($request->token->uid))->agent_report();
        return json($rsps);
    }

    //返水记录
    public function back_report(Request $request){
        $rsps = (new GameFactory($request->token->uid))->back_report();
        return json($rsps);
    }

    //分类会员返水配置
    public function water_configuration(Request $request){
        $rsps=(new GameFactory())->water_configuration();
        return json($rsps);
    }

    //首页游戏布局
    public function index_game_layout(Request $request){
        $rsps = (new GameFactory())->index_game_layout();
        return json($rsps);
    }

    //游戏平台对应余额
    public function get_third_balance(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_third_balance_run();
        return Respons::run($rsps);
    }

    //指定平台转入指定余额
    public function game_third_balance_in(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_third_balance_in_run();
        return Respons::run($rsps);
    }

    //指定平台转入指定余额
    public function game_third_balance_log(Request $request){
        $rsps = (new GameFactory($request->token->uid))->game_third_balance_log_run();
        return Respons::run($rsps);
    }

    //获取试玩游戏链接
    public function try_game(Request $request){
        $rsps = (new GameFactory())->try_game_run();
        return Respons::run($rsps);
    }

}
