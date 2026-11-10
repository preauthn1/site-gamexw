<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\{Games,UserThirds};

class Third_cmd extends Third
{

    private $pc = 'https://ctest.1win888.net';
    private $h5 = 'https://ctestsmart.1win888.net';

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
        $username = UserThirds::where(['ut_token'=>$data['token']])->field('(select rec_code from dq_user where id=ut_uid limit 1) as username')->find();
        $info = [
            'member_id'=>$username ? $username['username']:'',
            'status_code'=>$username ? 0:2,
            'message'=>$username ? 'Success':'Failed',
        ];

        $xml = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $xml .= "<authenticate>\n";
        foreach ($info as $key=>$val)
        {
            $xml.="<".$key.">".$val."</".$key.">\n";
        }
        $xml .= "</authenticate>";
        return ['return_type'=>'xml','data'=>$xml];
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
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $kds['tcusername'] = isset($datas['Data'][1]) ? $datas['Data'][1]:'';
        }

        if($func == 'GETUSERURL'){
            $cfgs = Games::getConfigs(null, $tcid);  //平台配置表
            $uname = Meta::initGetField('uname',$tcid,$gid,$uid); //用户名称
            $view = 'v1'; //风格
            $language = Meta::initGetField('lang',$tcid,$gid,$uid);  //语言
            $templatename = 'green'; //主题颜色
            $currencyId = $cfgs['trdcfg']['tc_currency']; //货币代码
            $token = Utils::uToken($uid.$tcid);  //生成token
            $url =  "/auth.aspx?lang={$language}&user={$uname}&token={$token}&currency={$currencyId}&templatename={$templatename}&view={$view}";
            $href = $ohterkvs['platform'] == 1 ? $this->pc : $this->h5;
            $kds['r_utoken'] = $token;
            $kds['tcgameurl'] = $href.$url;
        }
    }
}
