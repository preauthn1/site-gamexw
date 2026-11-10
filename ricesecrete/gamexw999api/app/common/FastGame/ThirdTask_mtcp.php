<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_mtcp extends ThirdTask implements TaskInterface {

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec() {
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

    public function dec($RawData,$HashKey,$HashIv){
        $FinalData =openssl_decrypt(base64_decode($RawData), 'aes-256-cbc',
            $HashKey, 0, base64_decode(base64_decode($HashIv)));
        $FinalData = json_decode($FinalData,true);
        return $FinalData;
    }

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag
     * @param $thistime
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    function setBetListStartFlag($gtag, $thistime, $data) {

        $Serial = 0;
        $config = $this->cfg;
        if(!empty($data['data'])){
            $IV = $config['trdcfg']['tc_api_signkey'];
            $HashKey = $config['trdcfg']['tc_api_prikey'];

            $ndata = $this->dec($data['data'],$HashKey,$IV);
            if(isset($ndata['rows'][0])){
                $Serial = 0;
                foreach ($ndata['rows'] as $k=>$v){

                    if ($v['Serial'] == 0 || $v['Serial'] > $Serial) {
                        $Serial = $v['Serial'];

                    }
                }
            }

        }


        //echo '<pre>';print_r($Serial);die;
        $func= $this->nowGetbetlistFunc;
        Utils::L('test', ['准备保存API配置值', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);

        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;

            // 页码归0
            $cfg[$gtag]['LastSerial'] = $Serial;
        }
        if ($upflag) $this->updateApiConfigValue($func, $cfg);
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag, $thistime) {
        //取前10分钟到前5分钟 为期5分钟的数据
        $func= $this->nowGetbetlistFunc;
        //$func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
        $LastSerial = $cfg['LastSerial'] ?? 0; //查询上一次执行的分页
        //Utils::L('taskfb',['读取到的函数配置',$cfg]);
        if(!isset($cfg['end_time']) || !isset($cfg['start_time'])){
            $cfg['lastruntime'] = 0;
            $cfg['lastUpdateDate'] = 0;
        }

        //业务代码 设置非元数据中的字段值
        if ($cfg['lastruntime'] == 0) {

            //初始化
            $start = time() - 600; //前10分钟
            $endtime = $thistime - 300; //前10分钟
        } else {
            if ($LastSerial > 0) {
                // 继续使用上次的时间
                $start = strtotime($cfg['start_time']);
                $endtime = strtotime($cfg['end_time']);
            } else {
                //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
                //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
                //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
                $start = $cfg['end']??$thistime - 300; //查询上一次执行的时间
                $endtime = $start;
                $start = $start - 300; //前5分钟
            }
        }
        $flags = [
            "end" => min($endtime + 240, $thistime), //当前执行时间 给下次执行用的
            "lastruntime" => $thistime,
            "end_time" => is_numeric($endtime) ? $endtime : strtotime($endtime), //当前执行时间 给下次执行用的
            "start_time" => is_numeric($start) ? $start : strtotime($start), //上次的执行时间减5分钟为这次的开始时间
            'LastSerial' => $LastSerial,
            'limit' => 1000,
        ];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag], $flags);
        //Utils::L('taskfb',['处理后的函数配置',$this->apiscfgvalues[$func][$gtag]]);
        //业务代码结束
        return $flags;
    }

    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param $gcode 游戏在三方平台的代码
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procBetData($gcode, $data) {

        return $data;
    }


    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){
        if(empty($rlt['data'])){
            return [];
        }

        $IV = $gcfg['trdcfg']['tc_api_signkey'];
        $HashKey = $gcfg['trdcfg']['tc_api_prikey'];
        $ndata = $this->dec($rlt['data'],$HashKey,$IV);

        if(isset($ndata['rows'][0])) {

            Utils::L("{$gcfg['trdcfg']['tc_id']}/ResultData",['获取注单数据', $ndata]);

        }else{
            Utils::L("{$gcfg['trdcfg']['tc_id']}/ResultData_error",['获取注单数据异常',$data]);
            return [];
        }

        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code'=> 'MTCP' ]]);
        foreach ($ndata['rows'] as $v) {
            $user = explode('-',$v['Account']);
            $gb_winlose = 2;
            $gb_price = Third::transExchangerate($v['BetTotal'],0);//投注金额
            $gb_winprice = Third::transExchangerate($v['Winnings'],0);//赢金额
            $gb_winprice  = $gb_price + $gb_winprice;
            if($gb_winprice > 0){

                $gb_winlose = 3;
            }
            $time = strtotime($v['DateCreate']);
            $rd[] = [
                'gb_op' => 'n',
                'gb_gid'=>Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['LotteryType'], 'tag', 'g_id'),
                'gb_uname'=> $user[1],
                'gb_winlose'=>$gb_winlose,
                'gb_bettime'=>strtotime($v['DateCreate']),
                'gb_overtime'=>strtotime($v['DateUpdate']),
                'gb_price'=>$gb_price,
                'gb_sn'=> $v['No'],
                'gb_winprice'=>$gb_winprice,
            ];
        }
        return $rd;
    }


    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid, $type) {
        //业务代码开始
        parent::transferBets($gid, $type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
        //业务代码结束
    }

    public function runFy($lasttime) {
        // TODO: Implement runJs() method.
    }

    public function runJs($lasttime) {
        // TODO: Implement runJs() method.
    }
}
