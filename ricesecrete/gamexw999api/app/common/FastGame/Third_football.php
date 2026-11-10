<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Third;

class Third_football extends Third
{
    public function test($cfgs)
    {
        return 'football';
    }

    /**
     * @inheritDoc
     */
    function MakeSign($config, &$data)
    {
        // TODO: Implement MakeSign() method.
    }

    /**
     * @inheritDoc
     */
    function CheckSign($config, $data)
    {
        // TODO: Implement CheckSign() method.
    }

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * 不需要处理的直接 return $in; 即可
     * @param mixed $in
     * @return int
     * @author twt326
     * @date 2022-09-13 16:32
     */
    function TokenTimestampTranser($in)
    {
        // TODO: Implement TokenTimestampTranser() method.
        return $in;
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }
}
