<?php


namespace app\common\FastGame;


use app\model\Games;
use app\common\FastGame\Base\{Game, Http, Meta, Redis, TaskInterface, Third, ThirdTask, Utils};
use app\common\payment\PayRequest;

class ThirdTask_zg extends ThirdTask implements TaskInterface
{


    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        // TODO 必须转三方时区
        date_default_timezone_set('Asia/Shanghai'); //转三方时区时间
        $cfgvalues = $this->cfgvalues;
        //执行任务
        $this->runThirdTasks();
        $this->runJs($cfgvalues['jsst']);
        $this->runFy($cfgvalues['fyst']);
        $this->runStat($cfgvalues['statst']);
        $this->updateConfigValue();
        //转转回项目时区
        date_default_timezone_set(env('DEFAULT_TIMEZONE', 'Asia/Shanghai')); //转三方时区时间
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
        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
            // 已完成
            if (!isset($cfg[$gtag]['index']) || $data['total'] <= $cfg[$gtag]['index']) {
                // 页码归0
                $cfg[$gtag]['index'] = 0;
            }
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {
        Third::initExtClass(['trdcfg' => ['tc_code' => 'ZG']]);
        $rd = [];

        foreach ($data as $key => $v) {
            if ($v['payout_amount'] > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }

            $gb_winprice = Third::transExchangerate($v['payout_amount'], 0, 2);
            $gbprice = Third::transExchangerate($v['bet_amount'], 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['game_id'], 'tag', 'g_id');
            $rd[] = [
                //1	Normal
                //2	Refund
                //3	Bet Refused
                //4	Bet Record Voided
                //5	Cancelled
                'gb_op' => $v['status'] == 1 ? 'c' : 'u',
                'gb_gid' => $gid,
                'gb_uname' => $v['member'],
                'gb_winlose' => $v['status'] == 1 ? $gb_winlose : 0,
                'gb_overtime' => strtotime($v['finish_at']),
                'gb_price' => $gbprice,
                'gb_sn' => $v['id'],
                'gb_bettime' => strtotime($v['bet_at']),
                'gb_winprice' => $gb_winprice,
                'gb_cusfield' => $v['id']
            ];
        }
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
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326 DBLIVE
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime)
    {
        //取前10分钟到前5分钟 为期5分钟的数据
        $func = 'GETBETLIST';
        $config = $this->cfg;

        $cfg = $this->getApiCfgvalues($func, $gtag);
//        $this->cfg=Games::getConfigs(null,$this->trdid);
//        Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        $old_page = $cfg['index'] ?? 0; //查询上一次执行的分页
        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0) { //初始化
            $start = time() - 600; //前10分钟
            $endtime = $thistime - 300; //前10分钟
        } else {
            if ($old_page > 0) {
                // 继续使用上次的时间
                $start = $cfg['start_time'];
                $endtime = $cfg['end_time'];
            } else {
                //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
                //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
                //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
                $start = $cfg['end']; //查询上一次执行的时间
//                if ($start + 3600 <= $thistime) {//超过一个小时
//                    $start = $thistime - 3600; //一个小时以前
//                    $endtime = $thistime;
//                } else {
                    $endtime = $start;
                    $start = $start - 300; //前5分钟
//                }
            }
        }
        $flags = [
            "lastruntime" => $thistime,
            "end" => min($endtime + 240, $thistime), //当前执行时间 给下次执行用的
            "start_time" => $start , //上次的执行时间减5分钟为这次的开始时间
            "end_time" => $endtime, //上次的执行时间为这次的结束时间
            'index' => $old_page,
            'limit' => 5000
        ];

        $agent_code = $config['trdcfg']['tc_tag'];
        $agent_key = $config['trdcfg']['tc_api_prikey'];
        $signKey = $config['trdcfg']['tc_api_signkey'];

        $channel = $config['trdcfg']['tc_token'];
        $flags['userCode'] = $agent_code;
        $flags['channel'] = $channel;

        $time =  array(
            "start_time"=> date('Y-m-d\TH:i:sP', $flags['start_time']),
            "end_time"=> date('Y-m-d\TH:i:sP', $flags['end_time'])
        );

        $signData['finish_time'] = $time;
        $signData['index'] = $flags['index'];
        $signData['limit'] = $flags['limit'];

        $jsign = json_encode($signData);

        $flags['finish_time'] = json_encode($time);
        $flags['userdata'] = $this->AESKeyEncryption($jsign, $agent_key);
        $flags['sign'] = $this->SignKeyEncryption($flags['userdata'],$signKey);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
        return $flags;
    }



    /**
     * 生成签名
     *
     * @param string $agent_code - agent代码
     * @param string $agent_key - agent密钥
     * @param string $timestamp - 时间戳
     * @param string $secret_key - 密钥
     * @return string - 生成的签名
     */
    public function AESKeyEncryption($d, $a)
    {
        $salt = openssl_random_pseudo_bytes(8);
        $salted = $dx = '';
        while (strlen($salted) < 48) {
            $dx = md5($dx . $a . $salt, true);
            $salted .= $dx;
        }
        $key = substr($salted, 0, 32);
        $iv = substr($salted, 32, 16);
        return base64_encode('Salted__' . $salt . openssl_encrypt($d . '', 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv));
    }

    public function SignKeyEncryption($a,$signKey)
    {
        return md5($a.$signKey, false);
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
