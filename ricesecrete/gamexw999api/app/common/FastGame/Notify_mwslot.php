<?php


namespace app\common\FastGame;

use app\common\model\{GameBetsModel, UserModel};
use app\common\FastGame\Base\{Game, Meta, Notify, Params, Redis, Third, User, Utils};
use app\model\{GameAward, GameBets, Games, Users, UserThirds};
use Firebase\JWT\JWT;
use think\facade\Db;

class Notify_mwslot extends Notify
{
    private $thirds_name = 'mwslot';

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    function notify($datas, $trdobj, $cfg)
    {
        return false;
    }

    /**
     * @param string $code
     * @param string $msg
     * @param array $data
     * @return array
     */
    private function ReturnData(string $code = '', string $msg = '', array $data = []): array
    {
        $arr['ret'] = $code;
        $arr['msg'] = $msg;
        $arr['userData'] = $data;

        return $arr;
    }


    private function PushLog($action, $msg, $data)
    {
        Utils::L($this->thirds_name, [$action,$msg, $data]);
    }


    //验证用户
    function AuthenticateToken($data, $trdobj, $cfg): array
    {
        //echo  json_encode($cfg);die;
        //$this->PushLog('AuthenticateToken','用户认证回调', json_encode($data));die;
        $arr = [];
        $msg = '查询用户错误';
        try {
            $EC_PRIVATE_KEY = str_replace("\\n", "\n", $cfg['trdcfg']['tc_api_prikey']);
            $MW_PUBLIC_KEY = str_replace("\\n", "\n", $cfg['trdcfg']['tc_token']);
            //其它配置多的，合并在token这里进行分割
            $token = explode('_',$cfg['trdcfg']["tc_tag"]);

            $response= $data['param'];
            $res_data = self::decrypt_response(json_encode($response),$EC_PRIVATE_KEY);


            //echo json_encode($res_data);die;
            $uid = UserModel::where('rec_code', $res_data['uid'])->value('rec_code');
            if(empty($uid)){
                $arr['ret'] = '2000';
                $arr['msg'] = '用户不存在！';
                $arr['userData'] = '';
            }else{
                $arr['ret'] = '0000';
                $arr['msg'] = '';
                $arr['userData']['uid'] = $uid;
                $arr['userData']['userName'] = $uid;
                $arr['userData']['userEnable'] = 'Y';
                $arr['userData']['userTest'] = $token[3];//后台配置，Y表示测试，N表示正式
                $arr['userData']['currency'] = $cfg['trdcfg']['tc_currency'];
            }
            $keyData = self::encrypt_key_data($arr,$MW_PUBLIC_KEY);
            $redata['key'] = $keyData[0];
            $redata['data'] = $keyData[1];
            return $redata;

        } catch (\Throwable $ex) {
            $msg = 'fail';
            $code = 5;
            $this->thirds_name = "{$cfg['trdcfg']['tc_id']}/error";
            $this->PushLog('auth','过程错误', [$ex->getMessage(), $ex->getTraceAsString()]);
        }
    }
    function aes_encrypt($key, $str) {
        $encrypted = openssl_encrypt($str, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        $data = base64_encode($encrypted);
        return $data;
    }

    function aes_decrypt($key, $str) {
        $encryptedData = base64_decode($str);
        $decrypted = openssl_decrypt($encryptedData, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $decrypted;
    }

    function rsa_encrypt($aesKey, $pubkey) {
        openssl_public_encrypt($aesKey, $encryptKey, $pubkey);
        $encryptKey = base64_encode($encryptKey);
        return $encryptKey;
    }

    function rsa_decrypt($ciphertext, $prikey) {
        $receiveKey = base64_decode($ciphertext);
        $ecPrivateKey = openssl_pkey_get_private($prikey);
        openssl_private_decrypt($receiveKey, $decryptAesKey, $ecPrivateKey);
        return $decryptAesKey;
    }

    function encrypt_key_data($data,$MW_PUBLIC_KEY) {
        $aesKey = self::generate_aes_key(); // 生成AESKEY 長度16
        $encryptKey = self::rsa_encrypt($aesKey, $MW_PUBLIC_KEY); // MW平台公鑰 加密 AESKEY 獲得 EC加密KEY

        $jsonStr = json_encode($data); // DATA 轉成 JSON字串
        $encryptData = self::aes_encrypt($aesKey, $jsonStr); // AESKEY 加密 JSON字串 獲得 EC加密DATA

        return [$encryptKey, $encryptData]; // 發送 EC加密KEY、EC加密DATA 給 MW
    }

    function decrypt_response($response,$EC_PRIVATE_KEY) {

        $response_json = json_decode($response, true);
        $encryptKey = $response_json["key"]; // MW加密KEY
        $encryptData = $response_json["data"]; // MW加密DATA

        $aesKey = self::rsa_decrypt($encryptKey, $EC_PRIVATE_KEY); // EC方私鑰 解密 MW加密KEY 獲得 AESKEY

        $decryptData = self::aes_decrypt($aesKey, $encryptData); // AESKEY 解密 MW加密DATA 獲得 DATA
        //echo_log("DecryptData: $decryptData");
        return json_decode($decryptData, true);
    }

    function generate_aes_key() {
        $aes = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0; $i < 16; $i++) {
            $aes .= $strPol[rand(0, $max)];
        }

        return $aes;
    }
}
