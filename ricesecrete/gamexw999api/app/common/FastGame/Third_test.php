<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;

class Third_test extends Third
{

    /**
     * @inheritDoc
     */
    function MakeSign($config, &$data)
    {
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = Http::ToUrlParams($data,false,['sign']);
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=".$config['cfg']['gc_api_prikey'];
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        $data['sign']=$result;
        return $result;
    }

    /**
     * @inheritDoc
     */
    function CheckSign($config, $data)
    {
        // TODO: Implement CheckSign() method.
        $sign=isset($data['data']['sign'])?$data['data']['sign']:null;
        if ($sign) {
            return $this->MakeSign($config, $data['data']) == $sign;
        }else{
            return false;
        }
    }

    function test(){
        return 'Third_test CLASS';
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


}
