<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\payment\PayRequest;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;

class ThirdTask_dgs extends ThirdTask implements TaskInterface
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
        $cfgvalues=$this->cfgvalues;
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
        Utils::L('test',['准备保存API配置值', 'gcode'=>$gtag, 'thistime'=>$this, 'cfg'=>$this->apiscfgvalues[$func]]);
        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
        }
        if ($upflag) $this->updateApiConfigValue($func,$cfg);
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){
        return $data ?? [];
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
        $gcfg = $this->cfg;
        $rd = [];
        $gb_winlose = 1;
        if ($data['isRevocation'] == 1) {
            if ($data['winOrLoss'] > 0) {
                $gb_winlose = 3;
            } else {
                $gb_winlose = 2;
            }
        } else{
            $gb_winlose = 0;
        }
        Third::initExtClass(['trdcfg' => ['tc_code'=> 'DGS' ]]);
        $gb_winprice = Third::transExchangerate($data['winOrLoss'],0,2);
        $gbprice = Third::transExchangerate($data['betPoints'],0,2);
        $gid = Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $data['tableId'], 'tag', 'g_id');
        $rd = [
            'gb_op' => 'n',
            'gb_gid'=>$gid,
            'gb_uname'=>$data['userName'],
            'gb_winlose'=>$gb_winlose,
            'gb_overtime'=> strtotime($data['calTime']),
            'gb_price'=>$gbprice,
            'gb_sn'=> $data['id'],
            'gb_bettime'=>strtotime($data['betTime']),
            'gb_winprice'=>$gb_winprice,
            'gb_cusfield'=>$data['id']
        ];
        try {
            $urls = $gcfg['trdcfg']['tc_api_urlpre']."/game/markReport/".$gcfg['trdcfg']['tc_tag'];
            $rand = get_rand_char(10);
            $token = md5($gcfg['trdcfg']['tc_tag'].$gcfg['trdcfg']['tc_api_signkey'].$rand);
            $body = json_encode([
                "token" => $token,
                "random" => $rand,
                'list'=>[$data['id']]
            ]);
            $header = array(
                'Content-Type: application/json'
            );
            $content = PayRequest::curlS($urls,$body,$header);
            Utils::L("{$gcfg['trdcfg']['tc_id']}/markReport",['markReport', $content, $urls, $body]);
        } catch (\Exception $e) {
            Utils::L("{$gcfg['trdcfg']['tc_id']}/markReportError",['markReport-error', $e->getMessage(), $e->getTraceAsString()]);
        }
        return $rd;
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag,$thistime)
    {
        $func = 'GETBETLIST';
        $config = $this->cfg;
        $rand = get_rand_char(10);
        $token = md5($config['trdcfg']['tc_tag'].$config['trdcfg']['tc_api_signkey'].$rand);
        $flags = [
            "token" => $token,
            "rand" => $rand,
        ];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        return $flags;
    }

    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid,$type){
        //业务代码开始
        parent::transferBets($gid,$type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
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
