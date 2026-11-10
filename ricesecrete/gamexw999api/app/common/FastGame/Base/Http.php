<?php


namespace app\common\FastGame\Base;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\Exception;

class Http
{
    /**
     * 输出xml字符
     * @param array $values
     * @reuturn string
     * @throws WxPayException
     **/
    public static function ToXml($values)
    {
        if (!is_array($values) || count($values) <= 0) {
            throw new Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @return array
     * @throws WxPayException
     */
    public static function FromXml($xml)
    {
        if (!$xml) {
            throw new Exception("xml数据异常！");
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;
    }

    /**格式化参数格式化成url参数
     * @param $values
     * @param $incNullValue 是否包含空值（0 '' null 等），签名时一般不包含空值 true:包含 false:不包含（default)
     * @param array $exKeys 排除KEY
     * @return string
     * @author twt326
     * @date 2022-08-26 17:38
     */
    public static function ToUrlParams($values, $incNullValue = false, $exKeys = [])
    {
        $buff = "";
        if (!is_array($values)) {
            return $buff;
        }
        foreach ($values as $k => $v) {
            if (!in_array($k, $exKeys) && ($incNullValue || !empty($v)) && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    public static function buildUrl($url, $params)
    {
        $p = self::ToUrlParams($params, true);
        if (substr($url, -1) == "?") {
            return $url . $p;
        } else {
            if (strpos($url, '?') > 0) {
                return $url . '&' . $p;
            } else {
                return $url . (empty($p) ? '' : ('?' . $p));
            }
        }
    }


    /** 根据要传送的数据中fg_posttype揸定的值来决定HTTP传输数据的类型,并清除数据中的保留属性
     * @param $postdata
     * @return string
     * @author twt326
     * @date 2023-02-15 20:56
     */
    private static function getHttpPostTyepTitle(&$postdata)
    {
        if (isset($postdata['fg_posttype'])) {
            $posttype = $postdata['fg_posttype'];
            unset($postdata['fg_posttype']);
            if (in_array($posttype, ['json', 'form', 'soap'])) {
                switch ($posttype) {
                    case 'form':
                        return 'form_params';
                        break;
                    case 'soap':
                        return 'body';
                        break;
                    default:
                        return $posttype;
                        break;
                }
            } else {
                return 'json';
            }
        } else {
            return 'json';//默认以json方式传送数据
        }
    }

    /** 获取http访问结果
     * TODO 可根据后续特别情况继续进行扩展
     * @param ResponseInterface $http
     * @return array|mixed
     */
    public static function getHttpRequestContent($http)
    {
        $contentType = trim($http->getHeaderLine('Content-Type'));
        if (stripos($contentType, 'text/csv') === 0) {
            $body = $http->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $stream = $body->detach();
            $rlt = [];
            while (($row = fgetcsv($stream)) !== false) {
                $rlt[] = $row;
            }
            fclose($stream);
        } elseif (stripos($contentType, 'text/html') === 0 || stripos($contentType, 'text/plain') === 0) {
            $rlt = $http->getBody()->getContents();
        } else {
            $content = $http->getBody()->getContents();
            if (empty($content)) {
                $rlt = [];
            } else {
                $rlt = json_decode($content, true);
                //兼容非json数据 可能是加密数据
                if (empty($rlt)) {
                    $rlt = $content;
                }
            }
        }
        return $rlt;
    }

    /** 合并可能存在的API附加模板数据到参数中
     * @param $ptype 合并模板参数类型 是GET POST HEAD 大小写不敏感
     * @param $pdata string|array 要合并的参数（post head参数要注意先json_decode）
     * @param $otherkvs 其中可能会带前前步处理的apitpl
     * @return void
     */
    public static function mergeApiTplData($ptype, &$pdata, $otherkvs)
    {
        $ptype = strtolower($ptype);
        if ($otherkvs && isset($otherkvs['apitpl']) && is_array($otherkvs['apitpl']) && isset($otherkvs['apitpl'][$ptype])) {
            if ($ptype == 'get') {
                $pdata = $pdata . (empty($pdata) ? '' : '&') . $otherkvs['apitpl'][$ptype];
            } else {
                if (!is_array($pdata)) {
                    $pdata = json_decode($pdata, true);
                }
                if (!$pdata) $pdata = [];
                $old = json_encode($pdata);
                //如果存在apitpl标签则替换掉原参数
                $kvs = ['apitpl' => $otherkvs['apitpl'][$ptype]];
                Meta::cusParseMateArrData($kvs, $pdata);
                //如果不存在则直接合并
                if ($old == json_encode($pdata)) {
                    $pdata = array_merge($pdata, $otherkvs['apitpl'][$ptype] ?? []);
                }
            }
        }
    }

    /**
     * 执行第三方接口
     * @param $apicfgs 游戏配置
     * @param $funcname 执行API名称
     * @param Third $pclass 继承的第三方游戏处理类对象
     * @param array $otherkvs 需要解析的其它参数值，如无为null 或者需要额外传递的参数
     * @return array|bool
     * @author twt326
     * @date 2022-08-27 21:36
     */
    public static function http($apicfgs, $funcname, $pclass, &$otherkvs)
    {
        try {
            $otherkvs['runfunc'] = $funcname;
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['http:in', $funcname]);
            if (isset($apicfgs['apis'][$funcname])) {
                $cfg = $apicfgs['apis'][$funcname];
            } else {
                return false;
            }
            $kfs = $cfg['ga_keyfield'] ? json_decode($cfg['ga_keyfield'], true) : [];//返回值关系
            $url = (!empty($apicfgs['apis'][$funcname]['ga_urlpre'])) ? $apicfgs['apis'][$funcname]['ga_urlpre'] : $apicfgs['trdcfg']['tc_api_urlpre'];
            $url .= $cfg['ga_route'];
            if (!empty($cfg['ga_gparam'])) {
                if (strpos($cfg['ga_route'], '?') > 0) {
                    $url .= "&";
                } else {
                    $url .= '?';
                }
                $url .= $cfg['ga_gparam'];
            }
            self::mergeApiTplData('get', $url, $otherkvs);//合并可能的模板参数
            $url = Meta::parseMateData($url, $apicfgs['trdcfg']['tc_id'], $apicfgs['g_id'], ($otherkvs && isset($otherkvs['uid'])) ? $otherkvs['uid'] : 0);
            /*if ($otherkvs){
                $url=Meta::cusParseMateData($otherkvs,$url,$pclass);
            }*/
            $postdata = json_decode($cfg['ga_pparam'], true);
            $posttype = self::getHttpPostTyepTitle($postdata);
            self::mergeApiTplData('post', $postdata, $otherkvs);//合并可能的模板参数
            $postdata = Meta::parseMateData($postdata, $apicfgs['trdcfg']['tc_id'], $apicfgs['g_id'], ($otherkvs && isset($otherkvs['uid'])) ? $otherkvs['uid'] : 0);
            if ($otherkvs) {
                $postdata = Meta::cusParseMateData($otherkvs, $postdata, $pclass);
            }
            //将签名信息加入otherkvs，用于header中传签名信息，如果有其它的需要header传送也可以在这里添加,注意，这里的标识要是保留字
            $headerdata = [];
            /*if (!empty($cfg['ga_hparam'])||!empty($cfg['ga_gparam'])) {
                if ($otherkvs == null) {
                    $otherkvs = [];
                }
                $sign = $pclass->MakeSign($apicfgs, $postdata);
                if (is_string($sign)) {
                    $otherkvs['head_sign'] = $sign;
                } else {
                    $otherkvs = array_merge($otherkvs, $sign);//用以适应在header中额个支持多个与签名相关的参数
                }
            }*/
            if (!empty($cfg['ga_hparam'])) {
                $headerdata = json_decode($cfg['ga_hparam'], true);
                self::mergeApiTplData('head', $headerdata, $otherkvs);//合并可能的模板参数
                $headerdata = Meta::parseMateData($headerdata, $apicfgs['trdcfg']['tc_id'], $apicfgs['g_id'], ($otherkvs && isset($otherkvs['uid'])) ? $otherkvs['uid'] : 0);

                /*if ($otherkvs){
                    $headerdata=Meta::cusParseMateData($otherkvs,$headerdata,$pclass);
                }*/
            }
            /*if (!empty($cfg['ga_gparam']) && $otherkvs) {
                $url=Meta::cusParseMateData($otherkvs,$url,$pclass);
            }*/

            //Utils::L('test',['http:params',$url,$postdata]);
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['point' => 'http:sign:before', 'url' => $url, 'data' => $postdata, 'headers' => $headerdata]);
            if ($cfg["ga_needsign"] == 1 || $cfg["ga_needsign"] == 3) {
                $pclass->signDatas = [
                    "runfunc" => $funcname,
                    "get" => $url,
                    "post" => $postdata,
                    "header" => $headerdata,
                    "otherkvs" => $otherkvs
                ];
                $sign = $pclass->MakeSign($apicfgs, $postdata);
                if (is_string($sign)) {
                    $otherkvs['head_sign'] = $sign;
                } else {
                    $otherkvs = array_merge($otherkvs, $sign);//用以适应在header中额个支持多个与签名相关的参数
                }
            }
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['point' => 'http:sign:after', 'url' => $url, 'data' => $postdata, 'headers' => $headerdata]);
            //执行顺序调整到最后，处理因$otherkvs传址方式将上次的签名或其它信息带回这里
            //将可能的签名信息加入otherkvs，用于header或url中传签名信息，如果有其它的需要传送也可以在这里添加,注意，这里的标识要是保留字
            if ($otherkvs) {
                $headerdata = Meta::cusParseMateData($otherkvs, $headerdata, $pclass);
                $url = Meta::cusParseMateData($otherkvs, $url, $pclass);
                $postdata = Meta::cusParseMateData($otherkvs, $postdata, $pclass);
                unset($postdata['els_access_key']);
            }
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['point' => 'http:cusParseMateData:after', 'url' => $url, 'data' => $postdata, 'headers' => $headerdata]);
        } catch (\Exception $ex) {
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/ThirdErrLogs", [
                'msg' => '执行http访问三方接口出错-无数据填充阶段',
                'suggest' => '根据track找到错误并修复',
                'track' => [
                    'method' => 'Http::http()',
                    'apicfgs' => $apicfgs,
                    'funcname' => $funcname,
                    'pclass' => $pclass ? $pclass::$extClassName : null,
                    'otherkvs' => $otherkvs,
                    'exception' => [$ex->getMessage(), $ex->getTraceAsString()]
                ]]);
            return false;
        }

        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }

        //访问
        $client = new Client();
        $flag = true;
        $rflag = true;
        try {
            Redis::close();
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['point' => 'http:request', 'url' => $url, 'type' => $cfg['ga_rtype'], 'data' => $postdata, 'headers' => $headerdata], true, true);
            $http = $client->request($cfg['ga_rtype'], $cfg['ga_rtype'] == 'get' ? self::buildUrl($url, $postdata) : $url, $cfg['ga_rtype'] == 'get' ? ['headers' => $headerdata, 'proxy' => $proxy] : [$posttype => $pclass->transFinallyPostdata($funcname, $postdata, $otherkvs), 'headers' => $headerdata, 'proxy' => $proxy]);
            $rlt = self::getHttpRequestContent($http);

            //如果转账ID存在，写入$otherkvs备用
            if (isset($postdata['traceId'])) {
                $otherkvs['traceId'] = $postdata['traceId'];
            }
            //if ($rlt || is_array($rlt)) {
            $heads = $http->getHeaders();
            if (is_string($rlt)) {
                //如果需要解密数据
                if (isset($cfg['ga_decode']) && $cfg['ga_decode'] == 1) {
                    $rawdata = $pclass->DecData($apicfgs, $rlt);
                    $rlt = json_decode($rawdata, true);
                } else {
                    $rlt['rawdata'] = $rlt;
                }
            } else {
                if (isset($cfg['ga_decode']) && $cfg['ga_decode'] == 1) {
                    $rlt = $pclass->DecData($apicfgs, $rlt);
                }
            }
            $rlt['return_headers'] = $heads;
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['http:rlt', $rlt]);
            //校验签名
            if ($cfg["ga_needsign"] == 2 || $cfg["ga_needsign"] == 3) {
                $tmpsignflag = true;
                if (isset($rlt['rawdata'])) {//防止某些三方返回的content-type=text/html的情况下其实数据还是JSON
                    try {
                        $tmpsignflag = $pclass->CheckSign($apicfgs, json_decode($rlt['rawdata'], true));
                    } catch (\Exception $ex) {
                        $tmpsignflag = false;
                    }
                }
                try {
                    $flag = $pclass->CheckSign($apicfgs, $rlt);
                } catch (\Exception $ex) {
                    $flag = false;
                }
                $flag = $flag || $tmpsignflag;
            }
            //较验返回值正确与否
            if ($flag && isset($kfs['rightflag'])) {
                if (isset($rlt['rawdata'])) {//防止某些三方返回的content-type=text/html的情况下其实数据还是JSON
                    $destr = json_decode($rlt['rawdata'], true);
                    if ($destr) {//如果不是JSON格式，则还是给原值
                        $rlt = $destr;
                    }
                }
                $rlt['httpcode'] = $http->getStatusCode();
                $rflag = ($kfs['rightflag'] == "httpcode") ? true : self::checkRltRight($rlt, $kfs['rightflag']);
                Utils::L("{$apicfgs['trdcfg']['tc_id']}/http", ['http:rlt:rightflag', $rflag, $rlt]);
                if ($rflag && $kfs['rightflag'] == "httpcode") {
                    //HTTPCODE判断的情况下，需要再次根据内容进行确认
                    $rflag = $pclass->httpcodeDataCheck($funcname, $rlt, $apicfgs, $otherkvs);
                }
                unset($rlt['httpcode']);
            }
            //return [$flag,$rflag,$rlt,isset($rlt['data'])?$rlt['data']:null];
            return $flag && $rflag ? (isset($rlt['rawdata']) ? $rlt['rawdata'] : $rlt) : false;
            /*}else{
                return false;
            }*/
        } catch (GuzzleException $ex) {
            Utils::L("{$apicfgs['trdcfg']['tc_id']}/ThirdErrLogs", [
                'msg' => '执行http访问三方接口出错-访问三方接口阶段',
                'suggest' => '根据track找到错误并修复',
                'track' => [
                    'method' => 'Http::http()',
                    'apicfgs' => $apicfgs,
                    'funcname' => $funcname,
                    'pclass' => $pclass ? $pclass::$extClassName : null,
                    'otherkvs' => $otherkvs,
                    'exception' => $ex->getMessage(),
                    'trace' => $ex->getTraceAsString()
                ]]);
            if (method_exists($ex, 'hasResponse')) {
                return $pclass->handleHttpException(
                    $ex->hasResponse(),
                    $ex->hasResponse() ? $ex->getResponse()->getStatusCode() : null,
                    $ex->getMessage(),
                    $ex->hasResponse() ? $ex->getResponse() : null,
                    $apicfgs,
                    $funcname,
                    $otherkvs);
            } else {
                return $pclass->handleHttpException(
                    false, null,
                    $ex->getMessage(), null,
                    $apicfgs,
                    $funcname,
                    $otherkvs);
            }
            //return false;
        }
    }

    /**
     * 判断返回结果是否正确
     *
     * @param $data
     * @param $rightflag
     * @return bool
     * @author twt326
     * @date 2022-09-08 18:10
     */
    public static function checkRltRight($data, $rightflag)
    {
        $dt = Utils::getDataFromArrayByStrKey($rightflag['key'], $data);
        if ($dt !== null) {
            $trueflag = true;
            $matchflag = true;
            $val = $rightflag['value'];
            $type = substr($val, 0, 1);
            if (in_array($type, ["!", "！"])) {//不等于某些值
                $val = substr($val, 1);
                $trueflag = false;
            } else {
                switch ($type) {
                    case 'V'://指定键存在值
                        return !empty($dt) && $dt != 'null' && $dt != 'undefined' && $dt != 'error';
                    case 'U'://指定键存在值,可谓空
                        return $dt != 'undefined' && $dt != 'error';
                    case 'E'://指定键的值符合正则规则
                        $val = substr($val, 1);
                        //TODO
                        break;
                }

            }
            if (strpos($val, ',')) {
                $val = explode(",", $val);
                $matchflag = in_array($dt, $val);
            } else {
                $matchflag = ($dt == $val);
            }
            return !($trueflag ^ $matchflag);
        } else {
            return false;
        }
    }
}
