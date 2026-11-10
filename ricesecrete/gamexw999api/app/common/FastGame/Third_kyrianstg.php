<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\ThirdTask;
use app\common\FastGame\Base\Utils;
use app\model\GameBetsTemp;
use app\model\Games;

class Third_kyrianstg extends Third
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
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = Utils::custGetSignStr($data,false,['sign']);
        //签名步骤二：在string后加入KEY
        $string = $string . $config['trdcfg']['tc_api_signkey'];
        //签名步骤三：MD5加密
        $string = sha1($string);
        //$data['signstr']=Utils::custGetSignStr($data,false,['sign']).$config['trdcfg']['tc_api_signkey'];//TEST
        $data['sign']=$string;
        return $string;
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
        Utils::L('test',['TokenTimestampTranser',$in,time()+$in-600]);
        return time()+$in-600;
    }

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }

    /**
     * 获取指定时间注单的DEMO，可以参考给任何API调用去做特殊处理
     * 注意这里的方法名不能与设置中的的全大写方法名一致（因为全大写的方法会直接调用封装方法 ），即这里的方法名不能全大写
     * 特别注意：本方法在此三方平台中暂时是无效的，仅用作DEMO
     * @param $apicfgs 三方平台配置
     * @param $otherkvs 要传入的非元数据参数
     *
     */
    function getbetlist($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        $rlt=Http::http($apicfgs,'GETBETLIST',self::$extClass,$otherkvs);
        //对获取的结果进行处理 这里只是以处理获取注单与游戏无关的情况，如果需要处理按游戏获取注单列表的任务的话，请参考 ThirdTask::runThirdTasks 方法中的$this->cfg['trdcfg']['tc_getbetlisttype']不等于all的情况
        //但事实上，核心点其实在于 Http::http方法 拿到三方返回数据之后的处理逻辑只要是正确的即可
        //如果是封装中有的方法 可以调用，比如：
        $cls=getc('ThirdTask_kyrianstg');
        if (!$cls) return false;
        /** @var ThirdTask $ttobj */
        $ttobj=new $cls();
        $ttobj->init($apicfgs['trdcfg']['tc_id'],$apicfgs['trdcfg']['tc_code']);
        $rlt = Http::http($apicfgs, 'GETBETLIST', $this, $otherkvs);
        if ($rlt) {
            //处理API配置值
            $rltKeyFieldCfg = json_decode($apicfgs['apis']['GETBETLIST']['ga_keyfield'], true);
            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
            //Utils::L('taskfb',['游戏列表',$bds]);
            $wds = [];
            $bds = $this->procBetDataPre($apicfgs, $bds, $rlt);
//                            Utils::L('taskfb',['处理后的游戏列表',$bds]);
            foreach ($bds as $bdk => $bdv) {
                //Utils::L('test',['处理',$bdv]);
                $bd = $this->procBetData('all', $bdv);
                //Utils::L('test',['处理结果',$bdv]);
                $bd = array_merge($bd, [
                    'gb_tcid'=>$apicfgs['trdcfg']['tc_id'],
                    'gb_intime' => time(),
                    'gb_betinfo' => json_encode($bdv)
                ]);
                //Utils::L('test',['合并结果',$bd]);
                array_push($wds, $bd);
            }
            //记录数据
            (new GameBetsTemp())->saveAll($wds);
            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
            ThirdTask::procBetTempData($this->cfg['trdcfg']['tc_useridentify'],'tcid',$apicfgs['trdcfg']['tc_id'],0);
            $ttobj->transferBets($apicfgs['trdcfg']['tc_id'],'all');

        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjIsInRjX25hbWUiOiJLeXJpYW5zdGcgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIyLTA5LTE2IDE0OjUzOjI4IiwidGNfY29kZSI6IktZUklBTlNURyIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvb3Blbi1hcGkua3lyaWFuc3RnLmNvbVwvZ2FtZWFwaVwvdjIiLCJ0Y19hcGlfcHJpa2V5IjoiVHdYYnh5ZVIyaEJBMkdFcGx3bW01IiwidGNfYXBpX3NpZ25rZXkiOiI5dENsdjBpa3hjbTRjd1c4bnl4QnkiLCJ0Y190YWciOiI3eXVTMnZFbHdaTldDOTc1ajlHVEQiLCJ0Y19jdXJyZW5jeSI6InVzZHQiLCJ0Y19leGNoYW5nZXJhdGUiOiIxMDAuMDAwMDAwMDAiLCJ0Y190b2tlbiI6Ik1JeGJZc3A2cmh1Rm80RmFZZDg5MyIsInRjX3Rva2VudGltZSI6MTY2OTA2MzIzMywidGNfcHJveHljb25maWciOm51bGwsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6IntcInpoLUNOXCI6XCJ6aC1DTlwiLFwiZW4tVVNcIjpcImVuLVVTXCIsXCJ2aS1WTlwiOlwidmktVk5cIixcInRoLVRIXCI6XCJ0aC1USFwiLFwiaWQtSU5cIjpcImlkLUlOXCIsXCJwdC1CUlwiOlwicHQtQlJcIn0iLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDIzMDEyMlwvMWYxMzI4NDZjZmI3NDQ5MGM3ZTc4Yjc1Y2RmZWQ4MGMuanBnIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMTAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6bnVsbCwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxMiwiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiREVQT1NJVCIsImdhX3JvdXRlIjoiXC90cmFuc2Zlcl9wbGF5ZXJfZnVuZCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcImF1dGhfdG9rZW5cIjpcInt0Y3Rva2VufVwiLFwibWVyY2hhbnRfY29kZVwiOlwie3RjdGFnfVwiLFwidXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImFjdGlvbl90eXBlXCI6XCJkZXBvc2l0XCIsXCJhbW91bnRcIjpcIntvX2Ftb3VudH1cIixcImV4dGVybmFsX3RyYW5zX2lkXCI6XCJ7b190cmFuc2lkfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInRjdHJhbnNiYW5pZFwiOntcImtleVwiOlwiZGV0YWlsLnRyYW5zYWN0aW9uX2lkXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NywiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQVVUSCIsImdhX3JvdXRlIjoiXC9nZW5lcmF0ZV90b2tlbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcIm1lcmNoYW50X2NvZGVcIjpcInt0Y3RhZ31cIixcInNlY3VyZV9rZXlcIjpcInt0Y2FwaWtleX1cIn0iLCJnYV90eXBlIjoiZ2V0YXV0aHRva2VuIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGN0b2tlblwiOntcImtleVwiOlwiZGV0YWlsLmF1dGhfdG9rZW5cIn0sXCJ0Y3Rva2VudGltZVwiOntcImtleVwiOlwiZGV0YWlsLnRpbWVvdXRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxNCwiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkFMQU5DRSIsImdhX3JvdXRlIjoiXC9xdWVyeV9wbGF5ZXJfYmFsYW5jZSIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiYXV0aF90b2tlblwiOlwie3RjdG9rZW59XCIsXCJ1aWRcIjpcInt1aWR9XCJ9IiwiZ2FfcHBhcmFtIjoie1wiYXV0aF90b2tlblwiOlwie3RjdG9rZW59XCIsXCJtZXJjaGFudF9jb2RlXCI6XCJ7dGN0YWd9XCIsXCJ1c2VybmFtZVwiOlwie3VuYW1lfVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjpudWxsLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGV0YWlsLmdhbWVfcGxhdGZvcm1fYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjpudWxsLCJnYV91cmxwcmUiOm51bGx9LHsiZ2FfaWQiOjksImdhX3RjaWQiOjIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvY2hhaW5cL3F1ZXJ5X2dhbWVfaGlzdG9yeSIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiYXV0aF90b2tlblwiOlwie3RjdG9rZW59XCIsXCJtZXJjaGFudF9jb2RlXCI6XCJ7dGN0YWd9XCIsXCJnYW1lX2NvZGVcIjpcIntsaXN0X2d0YWd9XCIsXCJmcm9tXCI6XCJ7bGlzdF9mcm9tfVwiLFwidG9cIjpcIntsaXN0X3RvfVwiLFwicGFnZV9udW1iZXJcIjpcIntsaXN0X3BhZ2V9XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRldGFpbC5nYW1lX2hpc3RvcnlcIn19IiwiZ2FfcmF0ZSI6NSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6IntcImNyYXNoXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE2NjYwMjUyODAsXCJjdXJ0aW1lXCI6MTY2NjAyNTI5MCxcImxpc3RfZ3RhZ1wiOlwiY3Jhc2hcIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcImRvdWJsZVwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMDYsXCJsaXN0X2d0YWdcIjpcImRvdWJsZVwiLFwibGlzdF9mcm9tXCI6XCIyMDIyMDkyNzIyMzcyN1wiLFwibGlzdF90b1wiOlwiMjAyMjEwMTgwMDQ3NTVcIixcImxpc3RfcGFnZVwiOjF9LFwiZGljZVwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMDEsXCJsaXN0X2d0YWdcIjpcImRpY2VcIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcImxpbWJvXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE2NjYwMjUyODAsXCJjdXJ0aW1lXCI6MTY2NjAyNTMyMSxcImxpc3RfZ3RhZ1wiOlwibGltYm9cIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcInBsaW5rb1wiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMzEsXCJsaXN0X2d0YWdcIjpcInBsaW5rb1wiLFwibGlzdF9mcm9tXCI6XCIyMDIyMDkyNzIyMzcyN1wiLFwibGlzdF90b1wiOlwiMjAyMjEwMTgwMDQ3NTVcIixcImxpc3RfcGFnZVwiOjF9LFwia2Vub1wiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMTYsXCJsaXN0X2d0YWdcIjpcImtlbm9cIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcIm1pbmVcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTY2NjAyNTI4MCxcImN1cnRpbWVcIjoxNjY2MDI1MzI2LFwibGlzdF9ndGFnXCI6XCJtaW5lXCIsXCJsaXN0X2Zyb21cIjpcIjIwMjIwOTI3MjIzNzI3XCIsXCJsaXN0X3RvXCI6XCIyMDIyMTAxODAwNDc1NVwiLFwibGlzdF9wYWdlXCI6MX0sXCJjcnlwdG9cIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTY2NjAyNTI4MCxcImN1cnRpbWVcIjoxNjY2MDI1Mjk2LFwibGlzdF9ndGFnXCI6XCJjcnlwdG9cIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcInRyaXBsZVwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzNDIsXCJsaXN0X2d0YWdcIjpcInRyaXBsZVwiLFwibGlzdF9mcm9tXCI6XCIyMDIyMDkyNzIyMzcyN1wiLFwibGlzdF90b1wiOlwiMjAyMjEwMTgwMDQ3NTVcIixcImxpc3RfcGFnZVwiOjF9LFwiaGlsb1wiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMTEsXCJsaXN0X2d0YWdcIjpcImhpbG9cIixcImxpc3RfZnJvbVwiOlwiMjAyMjA5MjcyMjM3MjdcIixcImxpc3RfdG9cIjpcIjIwMjIxMDE4MDA0NzU1XCIsXCJsaXN0X3BhZ2VcIjoxfSxcImNvaW5cIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTY2NjAyNTI4MCxcImN1cnRpbWVcIjoxNjY2MDI1Mjg1LFwibGlzdF9ndGFnXCI6XCJjb2luXCIsXCJsaXN0X2Zyb21cIjpcIjIwMjIwOTI3MjIzNzI3XCIsXCJsaXN0X3RvXCI6XCIyMDIyMTAxODAwNDc1NVwiLFwibGlzdF9wYWdlXCI6MX0sXCJ0b3dlclwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNjY2MDI1MjgwLFwiY3VydGltZVwiOjE2NjYwMjUzMzcsXCJsaXN0X2d0YWdcIjpcInRvd2VyXCIsXCJsaXN0X2Zyb21cIjpcIjIwMjIwOTI3MjIzNzI3XCIsXCJsaXN0X3RvXCI6XCIyMDIyMTAxODAwNDc1NVwiLFwibGlzdF9wYWdlXCI6MX19IiwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxMSwiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9jaGFpblwvcXVlcnlfZ2FtZV9sYXVuY2hlciIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiYXV0aF90b2tlblwiOlwie3RjdG9rZW59XCIsXCJ1aWRcIjpcInt1aWR9XCJ9IiwiZ2FfcHBhcmFtIjoie1wiYXV0aF90b2tlblwiOlwie3RjdG9rZW59XCIsXCJtZXJjaGFudF9jb2RlXCI6XCJ7dGN0YWd9XCIsXCJnYW1lX2NvZGVcIjpcIntndGFnfVwiLFwidXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImxhbmd1YWdlXCI6XCJ7bGFuZ31cIixcImhvbWVfbGlua1wiOlwiXCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkZXRhaWwuZ2FtZV91cmxcIn19IiwiZ2FfcmF0ZSI6MzYwMCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxMCwiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvY3JlYXRlX3BsYXllciIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcImF1dGhfdG9rZW5cIjpcInt0Y3Rva2VufVwiLFwibWVyY2hhbnRfY29kZVwiOlwie3RjdGFnfVwiLFwidXNlcm5hbWVcIjpcInt1bmFtZX1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJkZXRhaWwuaWRcIn0sXCJ0Y3VzZXJuYW1lXCI6e1wia2V5XCI6XCJkZXRhaWwucmVhbG5hbWVcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjoxMywiZ2FfdGNpZCI6MiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvdHJhbnNmZXJfcGxheWVyX2Z1bmQiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJhdXRoX3Rva2VuXCI6XCJ7dGN0b2tlbn1cIixcInVpZFwiOlwie3VpZH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJhdXRoX3Rva2VuXCI6XCJ7dGN0b2tlbn1cIixcIm1lcmNoYW50X2NvZGVcIjpcInt0Y3RhZ31cIixcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJhY3Rpb25fdHlwZVwiOlwid2l0aGRyYXdcIixcImFtb3VudFwiOlwie29fYW1vdW50fVwiLFwiZXh0ZXJuYWxfdHJhbnNfaWRcIjpcIntvX3RyYW5zaWR9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGN0cmFuc2JhbmlkXCI6e1wia2V5XCI6XCJkZXRhaWwudHJhbnNhY3Rpb25faWRcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfV19';
    }
}
