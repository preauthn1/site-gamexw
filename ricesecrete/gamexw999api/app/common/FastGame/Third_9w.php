<?php


namespace app\common\FastGame;

use app\common\game\GameRequest;
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_9w extends Third
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

        $kvs['cert'] = $config['trdcfg']['tc_api_prikey'];

        if($kvs['runfunc'] != 'GETBETLIST'){

            //$uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            //$kvs['vendor_member_id'] = $config['trdcfg']['tc_tag'].'_'.$uname;

        }
        if($kvs['runfunc'] == 'GETBETLIST'){


        }
        if($kvs['runfunc'] == 'GETUSERURL'){
            $kvs['agent'] = $config['trdcfg']['tc_tag'];
            $kvs['currency'] = $config['trdcfg']['tc_currency'];

        }
        if($kvs['runfunc'] == 'GETBALANCE'){
        }

        if(in_array($kvs['runfunc'],['DEPOSIT','WITHDRAW'])){

            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
            $kvs['traceId'] = $config['trdcfg']['tc_tag'].'_'.md5(date("YmdHis") . Utils::random(16));
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;

        if($func == 'GETUSERURL'){

            $uname = Meta::initGetField('uname',  $tcid, $config['g_id'], $uid);

            $kds['tcgameurl'] = $config['trdcfg']['tc_api_signkey'].'?userId='.$uname.'&key='.urlencode($datas['key']);

        }

        if($func == 'DEPOSIT'){

            $kds['tcbalance'] = $ohterkvs['o_amount'];

        }
        if($func == 'WITHDRAW'){

            $kds['tcbalance'] = $ohterkvs['o_amount'];


        }
        if($func == 'GETBALANCE'){

            $kds['tcbalance'] = $datas['results'][0]['balance'];

        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjQ1LCJ0Y19uYW1lIjoiOVciLCJ0Y19pbnRpbWUiOiIyMDI0LTA1LTIxIDE4OjMxOjUxIiwidGNfY29kZSI6IjlXIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGlpbmZvLnBwa2s3Ny5uZXQiLCJ0Y19hcGlfcHJpa2V5Ijoid2tWd1E4c01WcjI1MkVBOSIsInRjX2FwaV9zaWdua2V5IjoiaHR0cHM6XC9cL3Rlc3Q4MS5wcGtrNzcubmV0XC9hcGlXYWxsZXRcL3BsYXllclwvV0lDS0VUU1BST1wvbG9naW4iLCJ0Y190YWciOiJ0ZXN0cnMzaW5ybWEiLCJ0Y19jdXJyZW5jeSI6IjIiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiZW4iLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjEsImljb24iOiJodHRwczpcL1wvZDJwd3Vid3ZqdTF1ajYuY2xvdWRmcm9udC5uZXRcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA4MjNcLzdkMmI1MjE1ODgzODg3M2EyMzM5YTdkMDQxOWQ5NzY2LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAwMDAiLCJ0Y19tYXhfYmV0IjoiOTk5OTk5OTk5LjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvZDJwd3Vid3ZqdTF1ajYuY2xvdWRmcm9udC5uZXRcL3N0b3JhZ2VcL2ltZ1wvMjAyNDA4MjNcLzc5ZDZhYjE5ODdiZDg0NWY2N2JmYTgzMWQ2YWI4ZGFjLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOiIiLCJpc19uZXdfdGFiIjoxLCJ0Y19kaXNhYmxlX2FnZW50X2lkcyI6IjAifSwiYXBpcyI6W3siZ2FfaWQiOjEwNywiZ2FfdGNpZCI6NDUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQiLCJnYV9yb3V0ZSI6IlwvYXBpXC9hcGlXYWxsZXRcL1dJQ0tFVFNQUk9cL2RlcG9zaXQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwiY2VydFwiOlwie2NlcnR9XCIsXCJ0c0NvZGVcIjpcInt0cmFjZUlkfVwiLFwiYmFsYW5jZVwiOlwie2Ftb3VudH1cIixcInVzZXJJZFwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjpcIjFcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImFtb3VudFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjEwOCwiZ2FfdGNpZCI6NDUsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvYXBpXC9hcGlXYWxsZXRcL1dJQ0tFVFNQUk9cL2dldEJhbGFuY2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwiY2VydFwiOlwie2NlcnR9XCIsXCJhbGx1c2VyXCI6MCxcInVzZXJJZHNcIjpcInt1bmFtZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJzdGF0dXNcIixcInZhbHVlXCI6XCIxXCJ9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJyZXN1bHRzWzBdLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMDksImdhX3RjaWQiOjQ1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL2FwaVwvYXBpV2FsbGV0XC9XSUNLRVRTUFJPXC9xdWVyeUJldEhpc3RvcnlGb3JBbGxTdGF0dXMiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwiY2VydFwiOlwie2NlcnR9XCIsXCJzdGFydERhdGVcIjpcIntzdGFydF90aW1lfVwiLFwiZW5kRGF0ZVwiOlwie2VuZF90aW1lfVwiLFwicGFnZU51bWJlclwiOlwie3BhZ2V9XCIsXCJ0aW1lWm9uZVwiOjEsXCJiZXRTdGF0dXNcIjotMX0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjpcIjFcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1NDc1MTg5LFwiY3VydGltZVwiOjAsXCJ2ZXJzaW9uX2tleVwiOjIyMDMsXCJsYXN0X3ZlcnNpb25fa2V5XCI6MjAyMCxcImVuZF90aW1lXCI6XCIyMDI0LTA5LTA1IDAwOjE2XCIsXCJzdGFydF90aW1lXCI6XCIyMDI0LTA5LTA0IDAwOjE5XCIsXCJwYWdlXCI6MzU4LFwicGFnZV9zaXplXCI6MjAwMCxcImxhc3RVcGRhdGVEYXRlXCI6MTcyNTM4ODk2OTAwMH19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTI0LCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVDEiLCJnYV9yb3V0ZSI6IlwvYXBpXC9hcGlXYWxsZXRcL1dJQ0tFVFNQUk9cL3F1ZXJ5TWluaUdhbWVCZXRIaXN0b3J5Rm9yQWxsU3RhdHVzIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcImNlcnRcIjpcIntjZXJ0fVwiLFwic3RhcnREYXRlXCI6XCJ7c3RhcnRfdGltZX1cIixcImVuZERhdGVcIjpcIntlbmRfdGltZX1cIixcInBhZ2VOdW1iZXJcIjpcIntwYWdlfVwiLFwidGltZVpvbmVcIjoxLFwiYmV0U3RhdHVzXCI6LTF9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJzdGF0dXNcIixcInZhbHVlXCI6XCIxXCJ9LFwicl9saXN0XCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTczNDcwNCxcImN1cnRpbWVcIjowLFwiZW5kX3RpbWVcIjpcIjIwMjQtMDktMDUgMDI6MzVcIixcInN0YXJ0X3RpbWVcIjpcIjIwMjQtMDktMDQgMDI6MzVcIixcInBhZ2VcIjoxLFwicGFnZV9zaXplXCI6MjAwMCxcImxhc3RVcGRhdGVEYXRlXCI6MTcyNTY0ODQ4NTAwMH19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTI1LCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVDIiLCJnYV9yb3V0ZSI6IlwvYXBpXC9hcGlXYWxsZXRcL1dJQ0tFVFNQUk9cL3F1ZXJ5Q3VycmVudEJldHNCeUxhc3RVcGRhdGVEYXRlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcImNlcnRcIjpcIntjZXJ0fVwiLFwibGFzdFVwZGF0ZURhdGVcIjpcIntsYXN0VXBkYXRlRGF0ZX1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjpcIjFcIn0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI4MjAyMzI5LFwiY3VydGltZVwiOjAsXCJlbmRfdGltZVwiOlwiMjAyNC0wOS0yOCAxMToyNFwiLFwic3RhcnRfdGltZVwiOlwiMjAyNC0wOS0wNCAwMjo0OVwiLFwicGFnZVwiOjEsXCJwYWdlX3NpemVcIjoyMDAwLFwibGFzdFVwZGF0ZURhdGVcIjowLFwicGFnZTJcIjowfX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMTEsImdhX3RjaWQiOjQ1LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2FwaVwvYXBpV2FsbGV0XC9XSUNLRVRTUFJPXC9nZXRLZXkiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwiLFwiY2VydFwiOlwie2NlcnR9XCIsXCJ1c2VySWRcIjpcInt1bmFtZX1cIixcInVzZXJOYW1lXCI6XCJ7dW5hbWV9XCIsXCJleHBvc3VyZUxpbWl0XCI6LTEsXCJjb21taXNzaW9uXCI6MixcInRpbWVab25lXCI6MSxcImFnZW50XCI6XCJ7YWdlbnR9XCIsXCJjdXJyZW5jeVwiOlwie2N1cnJlbmN5fVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjoxfSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTEyLCJnYV90Y2lkIjo0NSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvYXBpXC9hcGlXYWxsZXRcL1dJQ0tFVFNQUk9cL3dpdGhkcmF3IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC1UeXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wiZmdfcG9zdHR5cGVcIjpcImZvcm1cIixcImNlcnRcIjpcIntjZXJ0fVwiLFwidHNDb2RlXCI6XCJ7dHJhY2VJZH1cIixcImJhbGFuY2VcIjpcInthbW91bnR9XCIsXCJ1c2VySWRcIjpcInt1bmFtZX1cIixcIndpdGhkcmF3dHlwZVwiOjB9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwic3RhdHVzXCIsXCJ2YWx1ZVwiOlwiMVwifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiYW1vdW50XCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}