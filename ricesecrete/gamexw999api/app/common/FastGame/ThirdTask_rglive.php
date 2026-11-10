<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Game, TaskInterface, Third, ThirdTask, Utils};


//ThirdTask_rglive
//RGLIVE
class ThirdTask_rglive extends ThirdTask implements TaskInterface
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
//        Utils::L('test', ['准备保存API配置值Flag', 'gcode' => $gtag, 'thistime' => $this, 'cfg' => $this->apiscfgvalues[$func]]);
        $cfg = json_decode(json_encode($this->apiscfgvalues[$func]), true);
        $this->updateApiConfigValue($func, $cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt)
    {

        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code' => 'RGLIVE']]);
        // 获取最$data后一项的id
        if (!empty($data)) {
            $lastid = end($data)['id'];
            $func = 'GETBETLIST';
            $this->apiscfgvalues[$func]['all']['MaxId'] = $lastid;
            $this->apiscfgvalues[$func]['all']['lastruntime'] = time();
        }
        foreach ($data as $key => $v) {
            $gb_winprice = Third::transExchangerate($v['winLose'] ?? 0, 0, 2);
            $gb_price = Third::transExchangerate($v['bet'] ?? 0, 0, 2);
            $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['desk'], 'tag', 'g_id');
            if (empty($gid)) {
                $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], "gc022001", 'tag', 'g_id');
            }
            $op = 'n';
            if ($gb_winprice > 0) {
                $gb_winlose = 3;
                $gb_winprice = floatval($gb_winprice) + floatval($gb_price);
            } else {
                $gb_winlose = 2;
                $gb_winprice = 0;
            }
//            $gb_price = 0;
            $rd[] = [
                'gb_op' => $op,
                'gb_gid' => $gid,
                'gb_uname' => $v['memberAccount'],
                'gb_winlose' => $gb_winlose,
                'gb_overtime' => strtotime($v['reportDT']),
                'gb_price' => $gb_price,
                'gb_sn' => $v['activeNo'] . "_" . $v['runNo'] . "_" . $v['betArea'],
                'gb_bettime' => strtotime($v['dateTime']),
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
        $cfg = $this->getApiCfgvalues($func, $gtag);
        $config = $this->cfg;
        // $cfg['MaxId'] = 0;
        $max_id = $cfg['MaxId'] ?? 0; //查询上一次执行的分页
        if (!isset($cfg['lastruntime'])) {
            $max_id = 0;
        } else {
            if ($cfg['lastruntime'] == 0) { //初始化
                $max_id = 0;
            }
        }
        //其它配置多的，合并在token这里进行分割
        $token = explode('|', $config['trdcfg']["tc_tag"]);
        $flags = [
            "SystemCode" => $token[0],
            "WebId" => $token[1],
            'MaxId' => $max_id,
            'Rows' => 2000
        ];

        $rg_live = new Third_rglive();
        $flags_data = $rg_live->MakeSign($config, $flags);
        foreach ($flags_data as $k => $v) {
            $flags[$k] = $v;
        }
        $flags['lastruntime'] = time();

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
