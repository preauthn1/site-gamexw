<?php


namespace app\common\FastGame;

use app\common\game\GameRequest;
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_saba extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     */
    function MakeSign($config, &$data)
    {
        return [];
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

        $kvs['vendor_id'] = $config['trdcfg']['tc_api_signkey'];
        //OddsType Table（赔率类型表）
        //代码 定义
        //0 特殊盘口,不属于任何一种市场盘口(仅出现于 sport_type=157, 168, 245 或bet_type=468, 469, 8700 或游戏组别為 Casino 及 Live Casino)
        //1 Malay Odds(马来盘)
        //2 China Odds(中国盘)
        //3 Decimal Odds(欧洲盘)
        //4 Indo Odds(印度尼西亚盘)
        //5 American Odds(美国盘)
        //6 Myanmar Odds (缅甸盘)

        //钱包标识符
        //1 : 沙巴体育
        //5 : AG
        //13 : Exchange

        //token字段放配置参数，比如：盘口_钱包编号,token[0]为盘口，token[1]为钱包编号
        $token = explode('_',$config['trdcfg']['tc_token']);
        //所有
        if($kvs['runfunc'] != 'GETBETLIST'){

            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['vendor_member_id'] = $config['trdcfg']['tc_tag'].'_'.$uname;

        }

        if($kvs['runfunc'] == 'GETBETLIST'){


        }else if($kvs['runfunc'] == 'REGISTER'){


            $kvs['operatorId'] = $config['trdcfg']['tc_tag'];
            $kvs['username'] = $config['trdcfg']['tc_tag'].'_'.$uname;
            $kvs['oddstype'] = $token[0];
            //币种说明
            //编号 代码 名称 汇率
            //2 MYR 马元 1:1
            //3 USD 美元 1:1
            //4 THB 泰铢 1:1
            //6 EUR 欧元 1:1
            //12 GBP 英镑 1:1
            //13 RMB 人民币 1:1
            //15 IDR 印度尼西亚盾 1:1000
            //20 UUS 测试货币(测试环境使用)
            //32 JPY 日圆 1:1
            //41 CHF 瑞士法郎 1:1
            //42 PHP 菲律宾比索 1:1
            //45 WON 韩元 1:1
            //46 BND 文莱元 1:1
            //48 ZAR 南非兰特 1:1
            //49 MXN 墨西哥比绍 1:1
            //50 CAD 加币 1:1
            //51 INH(VND) 越南盾 1:1000
            //52 DKK 丹麦克朗 1:1
            //53 SEK 瑞典克朗 1:1
            //54 NOK 挪威克朗 1:1
            //55 RUB 俄罗斯卢布 1:1
            //56 PLN 波兰兹罗提 1:1
            //57 CZK 捷克克朗 1:1
            //58 RON 罗马尼亚列伊 1:1
            //61 INR 印度卢比 1:1
            //70 MMK(MKK) 缅甸元 1:1000
            //71 KHR 柬埔寨瑞尔 1:1000
            //73 LIR(TRY) 土耳其里拉 1:1
            //79 KES 肯尼亚先令 1:1
            //80 GHS 加纳塞地 1:1
            //82 BRL 巴西雷亚尔 1:1
            //83 CLP 智利比索 1:1
            //84 COP 哥伦比亚比索 1:1
            //85 PEN 秘鲁新索尔 1:1
            //86 ARS 阿根廷比索 1:1
            //90 AED 阿联酋迪拉姆 1:1
            //93 LAK 老挝基普(寮国基普) 1:1000
            //96 USDT USDT 1:1
            //97 BDT 孟加拉国塔卡 1:1
            //121 PKR 巴基斯坦卢比 1:1
            //122 KZT 哈萨克坦吉 1:1
            //123 NPR 尼泊尔卢比 1:1
            //124 RMB2 人民币(只支援沙巴白牌中国版)1:1
            //125 INH2(VND) 越南盾(只支援沙巴白牌中国版)1:1000
            //126 TB2 泰铢 (只支援沙巴白牌中国版) 1:1
            //127 INR2 印度卢比 (只支援沙巴白牌中国版) 1:1
            //128 PE2 菲律宾比索 (只支援沙巴白牌中国版) 1:1
            //129 IN2(IDR) 印度尼西亚盾 (只支援沙巴白牌中国版) 1 : 1000
            //130 Won2 韩元 (只支援沙巴白牌中国版) 1:1
            //131 RM2 马元 1:1 (只支援沙巴白牌中国版)
            //132 US$2 美元 (只支援沙巴白牌中国版)1:1
            //133 TND 突尼斯第纳尔 1:1
            //134 ZMW 赞比亚克瓦查 1:1
            //135 TZS 坦桑尼亚先令 1:1
            //203 JPY2 日圆(只支援沙巴白牌中国版)1:1
            //204 EUR2 欧元(只支援沙巴白牌中国版)1:1
            //205 BRL2 巴西雷亚尔 (只支援沙巴白牌中国版)1:1
            //206 USDT2 USDT2 (只支援沙巴白牌中国版)1:1
            //207 NGN 尼日利亚奈拉 1:1
            //210 DZD 阿尔及利亚第纳尔 1:1
            //211 MAD 摩洛哥迪拉姆 1:1
            //212 FRF 法国法郎 1:1
            //215 BDT2 孟加拉国塔卡(只支援沙巴白牌中国版)1:1
            //216 EGP 埃及镑 1:1
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
            $kvs['maxtransfer'] = $config['trdcfg']['tc_max_bet'];
            $kvs['mintransfer'] = $config['trdcfg']['tc_min_bet'];

        }else if($kvs['runfunc'] == 'GETUSERURL'){

        }else if($kvs['runfunc'] == 'GETBALANCE'){

            $kvs['wallet_id'] = $token[1];

        }else{

            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            $kvs['traceId'] = $config['trdcfg']['tc_tag'].'_'.md5(date("YmdHis") . Utils::random(16));
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
            $kvs['wallet_id'] = $token[1];

        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

        if($func == 'GETBETLIST'){

        }

        if($func == 'DEPOSIT'){
            if($datas['message'] == 'pending'){
                //当返回状态为2时，就是pending情况，需要去确认状态
                $datas = checkTransfer($ohterkvs['traceId']);
            }
            if(isset($datas['Data']))
            {
                $kds['tcbalance'] = $datas['Data']['after_amount'] - $datas['Data']['before_amount'];
            }

        }
        if($func == 'WITHDRAW'){
            if($datas['message'] == 'pending'){
                //当返回状态为2时，就是pending情况，需要去确认状态
                $datas = checkTransfer($ohterkvs['traceId']);
            }

            if(isset($datas['Data']))
            {
                $kds['tcbalance'] = $datas['Data']['before_amount'] - $datas['Data']['after_amount'];
            }

        }
        if($func == 'GETBALANCE'){

            $kds['tcbalance'] = $datas['Data'][0]['balance'];

        }

    }

    function checkTransfer($traceId, $retry = 3){

        $config = $this->apicfgs;
        $vendor_id = $config['trdcfg']['tc_api_signkey'];
        //token字段放配置参数，比如：盘口_钱包编号,token[0]为盘口，token[1]为钱包编号
        $token = explode('_',$config['trdcfg']['tc_token']);

        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }
        $header[]="Content-type: application/x-www-form-urlencoded";
        $params = array(
            'vendor_id' => $vendor_id,
            'vendor_trans_id' => $traceId,
            'wallet_id' => $token[1]
        );
        $url = $config['trdcfg']['tc_api_urlpre'].'/CheckFundTransfer';
        $retryCount = 0;
        do {
            $res = json_decode(GameRequest::curl_post($url, $params, $header, $proxy), true);

            if($res['error_code'] == 2 && $retryCount < $retry){
                usleep(5000); // 5毫秒
                $retryCount++;
            } else {
                break; // 退出循环
            }

        } while ($retryCount < $retry);

        return $res??false;
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjk2LCJ0Y19uYW1lIjoiXHU2Yzk5XHU1ZGY0XHU0ZjUzXHU4MGIyXHVmZjA4XHU1OTFhXHU5NGIxXHU1MzA1XHVmZjA5IiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxODozMTo1MSIsInRjX2NvZGUiOiJTQUJBIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9xMno2dHNhLmJ3NjY4OC5jb21cL2FwaSIsInRjX2FwaV9wcmlrZXkiOiJSUzNUNjE2IiwidGNfYXBpX3NpZ25rZXkiOiI3eDlzY2I3cHl4IiwidGNfdGFnIjoiVDYxNiIsInRjX2N1cnJlbmN5IjoiMjAiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIzXzEiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiZW4iLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmZnLmdhbWVcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MjVcL2YyNDA4OWEyM2VhNGFiODU3MDY2OTA5YWZiZjZiZTEyLnN2ZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiOTk5OTk5OTk5LjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmZnLmdhbWVcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA3MjVcLzY1MjQ3OGUxOGIwZjZiN2IyZDZmNWYxZDdhOGM4YTRiLnN2ZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOiIiLCJpc19uZXdfdGFiIjowLCJ0Y19kaXNhYmxlX2FnZW50X2lkcyI6IjAifSwiYXBpcyI6W3siZ2FfaWQiOjIxMCwiZ2FfdGNpZCI6OTYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkNIRUNLVFJBTlNGRVIiLCJnYV9yb3V0ZSI6IlwvQ2hlY2tGdW5kVHJhbnNmZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwidmVuZG9yX2lkXCI6XCJ7dmVuZG9yX2lkfVwiLFwidmVuZG9yX3RyYW5zX2lkXCI6XCJ7dmVuZG9yX3RyYW5zX2lkfVwiLFwid2FsbGV0X2lkXCI6XCJ7d2FsbGV0X2lkfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCIwXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoyMDQsImdhX3RjaWQiOjk2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL0Z1bmRUcmFuc2ZlciIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6IntcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCIsXCJ2ZW5kb3JfaWRcIjpcInt2ZW5kb3JfaWR9XCIsXCJ2ZW5kb3JfbWVtYmVyX2lkXCI6XCJ7dmVuZG9yX21lbWJlcl9pZH1cIixcInZlbmRvcl90cmFuc19pZFwiOlwie3RyYWNlSWR9XCIsXCJhbW91bnRcIjpcInthbW91bnR9XCIsXCJjdXJyZW5jeVwiOlwie2N1cnJlbmN5fVwiLFwiZGlyZWN0aW9uXCI6MSxcIndhbGxldF9pZFwiOlwie3dhbGxldF9pZH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiMFwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiYW1vdW50XCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjA1LCJnYV90Y2lkIjo5NiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9DaGVja1VzZXJCYWxhbmNlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRzXCI6XCJ7dmVuZG9yX21lbWJlcl9pZH1cIixcIndhbGxldF9pZFwiOlwie3dhbGxldF9pZH1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiMFwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjIwNiwiZ2FfdGNpZCI6OTYsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvR2V0QmV0RGV0YWlsIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlcnNpb25fa2V5XCI6XCJ7dmVyc2lvbl9rZXl9XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOlwiMFwifSxcInJfbGlzdFwiOntcImtleVwiOlwiRGF0YS5CZXREZXRhaWxzXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU0NTQyNjcsXCJjdXJ0aW1lXCI6MCxcInZlcnNpb25fa2V5XCI6MzQwMjAsXCJsYXN0X3ZlcnNpb25fa2V5XCI6MjAyMH19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjA3LCJnYV90Y2lkIjo5NiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9HZXRTYWJhVXJsIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRcIjpcInt2ZW5kb3JfbWVtYmVyX2lkfVwiLFwicGxhdGZvcm1cIjpcIntwbGF0Zm9ybX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJlcnJvcl9jb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJEYXRhXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjA4LCJnYV90Y2lkIjo5NiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvQ3JlYXRlTWVtYmVyIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRcIjpcInt2ZW5kb3JfbWVtYmVyX2lkfVwiLFwib3BlcmF0b3JJZFwiOlwie29wZXJhdG9ySWR9XCIsXCJ1c2VybmFtZVwiOlwie3VzZXJuYW1lfVwiLFwib2Rkc3R5cGVcIjpcIntvZGRzdHlwZX1cIixcImN1cnJlbmN5XCI6XCJ7Y3VycmVuY3l9XCIsXCJtYXh0cmFuc2ZlclwiOlwie21heHRyYW5zZmVyfVwiLFwibWludHJhbnNmZXJcIjpcInttaW50cmFuc2Zlcn1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiZXJyb3JfY29kZVwiLFwidmFsdWVcIjowfSxcInRjdXNlcmtleVwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjA5LCJnYV90Y2lkIjo5NiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvRnVuZFRyYW5zZmVyIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcInZlbmRvcl9pZFwiOlwie3ZlbmRvcl9pZH1cIixcInZlbmRvcl9tZW1iZXJfaWRcIjpcInt2ZW5kb3JfbWVtYmVyX2lkfVwiLFwidmVuZG9yX3RyYW5zX2lkXCI6XCJ7dHJhY2VJZH1cIixcImFtb3VudFwiOlwie2Ftb3VudH1cIixcImN1cnJlbmN5XCI6XCJ7Y3VycmVuY3l9XCIsXCJkaXJlY3Rpb25cIjowLFwid2FsbGV0X2lkXCI6XCJ7d2FsbGV0X2lkfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImVycm9yX2NvZGVcIixcInZhbHVlXCI6XCIwXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJhbW91bnRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }

}