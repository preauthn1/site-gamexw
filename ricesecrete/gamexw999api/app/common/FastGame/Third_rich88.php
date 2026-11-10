<?php

namespace app\common\FastGame;

use app\common\FastGame\Base\Third;

class Third_rich88 extends Third
{

    function MakeSign($config, &$data)
    {
        $timestamp = strval(time());
        $text = "{$config['trdcfg']['tc_tag']}{$config['trdcfg']['tc_api_prikey']}{$timestamp}";
        $head_sign = hash('sha256', $text);
        return compact('head_sign',  'timestamp');
    }

    function CheckSign($config, $data)
    {
        $header = $data['header'];
        $text = "{$config['trdcfg']['tc_tag']}{$config['trdcfg']['tc_api_prikey']}{$header['timestamp']}";
        $head_sign = hash('sha256', $text);
        return $head_sign === $header['api-key'];
    }

    function TokenTimestampTranser($in)
    {
        return $in;
    }

    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }
}