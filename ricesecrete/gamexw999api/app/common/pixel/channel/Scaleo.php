<?php

namespace app\common\pixel\channel;

use app\common\lib\Util;
use app\common\pixel\Channel;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use think\facade\Log;
use Carbon\Carbon;

class Scaleo extends Channel {

    // 事件名称映射数组
    protected $events = [
        'reg' => 'reg',
        'first_recharge' => 'ftd',
        'recharge' => 'dep',
        'withdraw' => 'wdr',
        'refund' => 'chb'
    ];
    public function init($config) {
        if (!isset($config['token']) || !isset($config['code'])) {
            throw new \Exception('token和code不能为空');
        }
        $this->token = $config['token'];
        $this->code = $config['code'];
        $this->config = $config;
    }

    private function getParams(string $eventName, array $customData, string $eventId, array $userData = null) {
        $type = $this->getEventName($eventName);
        $params = [
            'timestamp' => Carbon::now('UTC')->format("Y-m-d H:i:s"),
            'type' => $this->getEventName($eventName),
            'click_id' => $userData['click_id'],
            'adv_user_id' => $userData['uid'],
            'event_id' => $eventId
        ];
        $type_params = [];
        if ($type == 'ftd' || $type == 'dep' || $type == 'wdr' || $type == 'chb') {
            $type_params = [
                'amount' => $customData['value'],
                'currency' => $customData['currency']
            ];
        }
        return array_merge($params, $type_params);
    }
    // https://pazuru.scaletrk.com/click?o=1&a=2&c=1&sub_id0=111
    public function send(string $eventName, array $customData, string $eventId, array $userData = null) {
        if (!isset($userData['click_id']) || empty($userData['click_id'])) {
            return false;
        }
        $params = $this->getParams($eventName, $customData, $eventId, $userData);
        $url = "{$this->code}/api/v2/network/tracking/event?api-key={$this->token}";
        try {
            $client = new Client();
            $response = $client->post($url, [
                'json' => $params
            ]);
            $contents = $response->getBody()->getContents();
            $result = json_decode($contents, true);
            return $result;
        } catch (\Exception $e) {
            Log::error(['Scaleo Pixel Error:' . $e->getMessage(), $params]);
        }

        return null;
    }
}