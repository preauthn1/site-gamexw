<?php
namespace app\common\lib;
use app\common\payment\PayRequest;
use think\facade\Log;
use Twilio\Rest\Client;

class Twilio
{
    protected $client;
    protected $service_id;


    public function __construct($config)
    {
        $sid = $config['sid'];
        $token = $config['token'];
        $this->service_id = $config['service_id'];
        $this->client = new Client($sid, $token);
    }


    public function sendSms($to)
    {
        try {
            $verification =  $this->client->verify->v2
                ->services($this->service_id)
                ->verifications->create(
                    $to, // to
                    "sms" // channel
                );
            Log::write(['sendSms', $verification->status, $verification->valid], "sms");
            if($verification -> valid){
                return true;
            }else{
                Log::write(['sendSms_error', $verification->status, $verification->valid], "sms");
                return false;
            }
        } catch (\Exception $e) {
            Log::write(['smsError', $e->getMessage()], "sms");
            // 处理异常
            return false;
        }
    }

    public function checkCode($to, $code){
        try {
            $verification = $this->client->verify->v2
                ->services($this->service_id)
                ->verificationChecks->create([
                    "to" => $to,
                    "code" => $code
                ]);
            Log::write(['sendSms', $verification->status, $verification->valid], "checkCode");
            if($verification -> valid){
                return true;
            }else{
                Log::write(['sendSms_error', $verification->status, $verification->valid], "checkCode");
                return false;
            }
        } catch (\Exception $e) {
            Log::write(['smsError', $e->getMessage()], "sms");
            // 处理异常
            return false;
        }
    }




}
