<?php


namespace app\common\FastGame;


use app\common\model\GameBetsModel;
use app\model\Games;
use app\common\FastGame\Base\{Game, Http, Meta, Redis, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

//ThirdTask_pgsoft
//PGSOFT
class ThirdTask_pgsoft extends ThirdTask implements TaskInterface
{
    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        $cfgvalues = $this->cfgvalues;
        //执行任务
        $this->runThirdTasks();
        $this->runJs($cfgvalues['jsst']);
        $this->runFy($cfgvalues['fyst']);
        $this->runStat($cfgvalues['statst']);
        $this->updateConfigValue();
    }

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag
     * @param $thistime
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    function setBetListStartFlag($gtag, $thistime, $data)
    {
        $func = 'GETBETLIST';
        Utils::L('test',
            ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = true;
//         if (isset($cfg[$gtag])) {
//             $upflag = true;
//             // 已完成
// //            if ($data['total_page'] <= $cfg[$gtag]['page']) {
// //                // 页码归0
// //                $cfg[$gtag]['page'] = 0;
// //            }
//         }
        if ($upflag) {
            $this->updateApiConfigValue($func, $cfg);
        }
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code' => 'PGSOFT']]);
        // 获取最$data后一项的id
        $lastid = end($data)['id'];
        foreach ($data as $key => $v) {
            $gb_winprice = Third::transExchangerate($v['payout'] ?? 0, 0, 2);
            $gb_price = Third::transExchangerate($v['bet'] ?? 0, 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['game_id'], 'tag', 'g_id');
            if(empty($gid)){
                print_r($v['game_id']."游戏不存在");
            }

            if ($gb_winprice > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }
            $op = 'n';

            $rd[] = [
                'gb_op'       => $v['gb_op'] ?? $op,
                'gb_gid'      => $gid,
                'gb_uname'    => $v['member'],
                'gb_winlose'  => $gb_winlose,
                'gb_overtime' => $v['end_time'],
                'gb_price'    => $gb_price,
                'gb_sn'       => $v['transaction_id'],
                'gb_bettime'  => $v['end_time'],
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['bet_id']
            ];
        }
        $func = 'GETBETLIST';
        $this->apiscfgvalues[$func]['all']['versionkey'] = $lastid;
        return empty($rd) ? [] : $rd;
    }


    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param $gcode 游戏在三方平台的代码
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procBetData($gcode, $data)
    {
        return $data;
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param  string  $gtag  游戏三方代码
     * @param  int  $thistime  当前调用时间节点
     * @return mixed
     * @author twt326 DBLIVE
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime)
    {
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
//        $this->cfg=Games::getConfigs(null,$this->trdid);
//        Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        //业务代码 设置非元数据中的字段值
        $versionkey = $cfg['versionkey'] ?? 1; //查询上一次执行的分页
        // $versionkey = 2624494;

        if ($cfg['lastruntime'] == 0) { //初始化
            $versionkey = 0;
        }
        $flags = [
            'versionkey' => $versionkey,
            'lastruntime'=>time()
        ];
        $config = $this->cfg;
        $flags['operatorcode'] = $config['trdcfg']['tc_api_prikey'];
        // Signature = MD5(operatorcode + secret_key), then convert to uppercase 加密后转大写.
        $flags['signature'] = strtoupper(md5($config['trdcfg']['tc_api_prikey'].$config['trdcfg']['tc_api_signkey']));
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
    }

    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid, $type)
    {
        //业务代码开始
        parent::transferBets($gid, $type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
        //业务代码结束
    }

    public function runFy($lasttime)
    {
        // TODO: Implement runJs() method.
    }

    public function runJs($lasttime)
    {
        // TODO: Implement runJs() method.
    }
}
