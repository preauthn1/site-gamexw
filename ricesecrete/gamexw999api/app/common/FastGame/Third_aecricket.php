<?php


namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games};
use app\common\FastGame\Base\{Game, Http, Meta, Third, ThirdTask, Utils};

class Third_aecricket extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:29
     */
    function MakeSign($config, &$data)
    {
        return true;
    }

    /**验证签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    function CheckSign($config, $data)
    {
        // 此三方站回复数据无签名
        return true;
    }

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * @param mixed $in
     * @return int
     * @author twt326
     * @date 2022-09-13 16:32
     */
    function TokenTimestampTranser($in)
    {
        return $in;
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $g_tcid = isset($config['g_tcid']) ? $config['g_tcid']:$config['trdcfg']['tc_id'];
        $gid = $config['g_id'] > 0 ?  $config['g_id'] : Game::getGamesInfoBy($g_tcid, 'aecricket', 'code', 'g_id');
        if($kvs['runfunc'] != 'GETAUTH'){
            $token = Meta::initGetField('tctoken',$g_tcid,$gid,$uid);
            $kvs['token'] = 'bearer '.$token;
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $kds['tcusername'] = $datas['User']['user_id'];
        }
        if($func == 'GETBALANCE'){
            $kds['tcbalance'] =  isset($datas['result'][0]['balance']) ? $datas['result'][0]['balance']:0;
        }
        if($func == 'WITHDRAW'){
            $kds['tcbalance'] = trim($datas['change'],'-');
        }
    }

    //外部调用任务方法
    function get_game_logs($apicfgs,$otherkvs){
        $kvs = [];
        Third::run($apicfgs,'GETAUTH',$kvs);
        $otherkvs['start'] = date("Y-m-d\TH:i:s\Z",$otherkvs['start']);
        $otherkvs['end'] = date("Y-m-d\TH:i:s\Z",$otherkvs['end']);
        $cls=getc('ThirdTask_aecricket');
        if (!$cls) return false;
        /** @var ThirdTask_fb $ttobj */
        $ttobj=new $cls();
        $ttobj->init($apicfgs['trdcfg']['tc_id'],$apicfgs['trdcfg']['tc_code']);
        $func = 'GETBETLIST';
        for ($i=0;$i<=5;$i++){
            $str = $func.$i;
            $rlt = Http::http($apicfgs, $str, $this, $otherkvs);
//            Utils::L('AE_cricket',[$rlt]);
            if ($rlt) {
                //处理API配置值
                $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST0']['ga_keyfield'], true);
                $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
                $wds = [];
                $bds = $ttobj->procBetDataPre($apicfgs, $bds, $rlt);
                foreach ($bds as $bdk => $bdv) {
                    $bd = $ttobj->procBetData('all', $bdv);
                    $bd = array_merge($bd, [
                        'gb_tcid'=>$apicfgs['trdcfg']['tc_id'],
                        'gb_intime' => time(),
                        'gb_betinfo' => json_encode($bdv)
                    ]);
                    array_push($wds, $bd);
                }
                (new GameBetsTemp())->saveAll($wds);
                ThirdTask::procBetTempData($apicfgs['trdcfg']['tc_useridentify'], 'tcid', $apicfgs['trdcfg']['tc_id'], 0);
                $ttobj->transferBets($apicfgs['trdcfg']['tc_id'], 'all');

            }
        }
    }
}
