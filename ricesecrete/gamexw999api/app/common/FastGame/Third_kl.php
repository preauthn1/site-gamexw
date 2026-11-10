<?php


namespace app\common\FastGame;


use app\common\game\GameRequest;
use app\common\lib\Util;
use app\common\model\GameBetsModel;
use app\model\GameBets;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_kl extends Third
{

    const PICK3 = 'pick3';
    const MATKA = 'matka';
    const KERALA = 'kerala';

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
        $SecretKey = $config['trdcfg']["tc_api_signkey"];

        if(isset($data['sign']))$data['sign']='{sign}';

        $sign = md5($data['Data'] . $SecretKey);

        return compact('sign');
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
        $SecretKey = $config['trdcfg']["tc_api_signkey"];
        $param = $data['param'];
        $_sign = md5($param['Data'] . $SecretKey);
        // 生成签名并校验，怎么urldecode掉了呢
        if ($_sign == $param['Sign']) {
            return true;
        }
        return false;
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

        if($kvs['runfunc'] == 'GETUSERURL') {
            $username = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['sdata'] = json_encode(['acc'=>$username]);
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if( $func == 'GETUSERURL' ){
            if( $datas['code'] == 0 ){
                $kds['tcgameurl'] = json_decode($datas['data'], true)['URL'].'&game=' . $config['g_code'];
            }
        }
        if($func == 'GETBETDETAIL') {
            $kds['tcgamedetailurl'] = false;
            $result = $this->_request($ohterkvs['betInfo'], $config['trdcfg'], $ohterkvs['betInfo']['gb_cusfield']);
            if( $result ){
                $kds['tcgamedetailurl'] = $this->handleDetailResult($result, $ohterkvs['betInfo']['gb_cusfield']);
            }
        }
    }

    //请求
    public function _request($hotBet, $platformDetail, $type){
        switch ($type){
            case Third_kl::KERALA:
                $_url = '/player_kerala_bet_log_by_order_id';
                $resultField = 'KeralaBetLog';
                break;
            case Third_kl::PICK3:
                $_url = '/player_pick3_bet_log_by_order_id';
                $resultField = 'Pick3BetLog';
                break;
            case Third_kl::MATKA:
                $_url = '/player_matka_bet_log_by_order_id';
                $resultField = 'MatkaBetLog';
                break;
            default:
                return false;
        }
        $url = $platformDetail['tc_api_urlpre'] . $_url;
        $params = [
            'Data' => json_encode([
                'type' => 1,
                'platform_order_id' => $hotBet['gb_sn'],
            ]),
            'PlatformCode' => $platformDetail['tc_tag'],
        ];
        $params['Sign'] = md5($params['Data'].$platformDetail['tc_api_signkey']);
        $header = [
            'Content-type: application/x-www-form-urlencoded',
        ];
        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }
        $res = GameRequest::curl_post($url, http_build_query($params), $header, $proxy);
        $res = json_decode($res, true);
        if( !$res || !isset($res['code']) || $res['code'] != 0 ){
            return false;
        }
        $result = json_decode($res['data'], true);
        return $result[$resultField][0] ?? ($result[$resultField] ?? []);
    }

    //处理详情结果
    private function handleDetailResult($thirdResult, $type){
        switch ($type){
            case Third_kl::KERALA:
                $resultField = 'KeralaLogNo';
                break;
            case Third_kl::PICK3:
                $resultField = 'Pick3LogId';
                break;
            case Third_kl::MATKA:
                $resultField = 'MatkaLogId';
                break;
            default:
                return false;
        }
        $statusArr = [-1 => '未知', 0 => '未开奖', 1 => '未中奖', 2 => '未领取', 3 => '已领取'];
        $arr = [];
        $arr[] = [
            'name' => lang('局号'),
            'value' => $thirdResult[$resultField] ?? '无',
        ];
        $arr[] = [
            'name' => lang('流水号'),
            'value' => $thirdResult['UserId'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('购买订单号'),
            'value' => $thirdResult['BuyOrderNo'] ?? '',
        ];
        $arr[] = [
            'name' => lang('购买号码'),
            'value' => $thirdResult['BuyingNumbers'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('购买数量'),
            'value' => $thirdResult['Quantity'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('购买总金额'),
            'value' => isset($thirdResult['Amount']) && $thirdResult['Amount'] ? $thirdResult['Amount'] / 1000 : '无',
        ];
        $arr[] = [
            'name' => lang('克拉拉结算状态'),
            'value' => $statusArr[$thirdResult['Status']?? -1],
        ];
        $arr[] = [
            'name' => lang('开奖号码'),
            'value' => $thirdResult['WinningNumbers'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('奖级'),
            'value' => $thirdResult['Award'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('中将金额'),
            'value' => isset($thirdResult['Prize']) && $thirdResult['Prize'] ? $thirdResult['Prize'] / 1000 : '无',
        ];
        $arr[] = [
            'name' => lang('下单时间'),
            'value' => $thirdResult['CreatedAt'] ?? '无',
        ];
        $arr[] = [
            'name' => lang('结算时间'),
            'value' => $thirdResult['UpdatedAt'] ?? '无',
        ];
        return $arr;
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjk1LCJ0Y19uYW1lIjoiS2VyYWxhIExvdHRlcnkiLCJ0Y19pbnRpbWUiOiIyMDIzLTAxLTE0IDE1OjA3OjAzIiwidGNfY29kZSI6ImtsIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9zZXJ2ZXIucnVubmluZzEwLnR2XC9hcGkiLCJ0Y19hcGlfcHJpa2V5IjoiIiwidGNfYXBpX3NpZ25rZXkiOiJrazhNUGlPRUp2IiwidGNfdGFnIjoiUlMzVDYxNiIsInRjX2N1cnJlbmN5IjoiSU5SIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6ImVuIiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5mZy5nYW1lXC9zdG9yYWdlXC9pbWdcLzIwMjQwNzI0XC8yMjI0NzcyNGVmNGMxM2MwZTkwNTRiOGU5MjgwNzRhOC5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMS4wMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjoiW1wiVEhcIl0iLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmZnLmdhbWVcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MjRcL2FiZDZhOTNiMDBjNjliYTc3NmZiNGY0MzcxYTllZjkyLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOiIiLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjIwNCwiZ2FfdGNpZCI6OTUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVERFVEFJTCIsImdhX3JvdXRlIjoiXC9vayIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IiIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCIxXCJ9LFwidGNnYW1lZGV0YWlsdXJsXCI6e1wia2V5XCI6XCJkYXRhLm1zZ1wifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6Imh0dHBzOlwvXC90aHJlZS5hcGlmZy5jb20ifSx7ImdhX2lkIjoyMDMsImdhX3RjaWQiOjk1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2NyZWF0X29yX2RlZmF1bHRfcGxheWVyX2xvZ2luIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiRGF0YVwiOlwie3NkYXRhfVwiLFwiU2lnblwiOlwie3NpZ259XCIsXCJQbGF0Zm9ybUNvZGVcIjpcInt0Y3RhZ31cIixcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIjBcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEuVVJMXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
