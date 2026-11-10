<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\lib\Util;
use app\common\model\AdsChannelModel;
use app\common\model\AdsModel;
use app\common\service\PixelService;
use app\common\service\ReportService;
use app\common\Utils;
use app\common\validate\AdsChannelValidate;
use app\common\validate\AdsValidate;
use Carbon\Carbon;
use think\exception\ValidateException;
use think\facade\Db;

class Ads extends Base
{

    protected $middleware = ['ManageCheckLogin', "Language"];

    public function index()
    {
        $name = $this->request->param('name/s');
        $where = [];
        if (!empty($name)) {
            $where[] = ["ad_name", "LIKE", "%{$name}%"];
        }
        [$data, $total] = $this->getDataList(new AdsModel(), $where, null, 'ad_id DESC');

        return $this->success([
            'total' => $total,
            'data' => $data,
            'ad_type' => Utils::getOptionsByConfig(config('custom.ad_type'))
        ]);
    }


    //添加
    public function add()
    {
        if ($this->request->has('add')) {
            $info = $this->request->param('info/a');
            $model = new AdsModel();
            //验证数据
            try {
                validate(AdsValidate::class)->check($info);
            } catch (ValidateException $e) {
                return $this->error($e->getError());
            }
            if (isset($info['ad_id']) && $info['ad_id'] > 0) {
                $old = $model->where('ad_id', $info['ad_id'])->find();
                if ($old) {
                    Utils::addOptLog($old->toArray(), $info, 'ads', '修改广告商');
                }
            } else {
                Utils::addOptLog($info, '', 'ads', '增加广告商');
            }
            return $this->save($model, $info, false, 'ad_id');
        }
        if ($this->request->has('find')) {
            $id = $this->request->param('ad_id');
            if (empty($id)) {
                return $this->error('数据不存在');
            }
            $model = new AdsModel();
            $res = $model->where("ad_id", $id)->find();
            return $this->success(['data' => $res]);
        }
        return $this->success();
    }

    //删除
    public function delete()
    {
        $id = $this->request->param('ad_id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new AdsModel();
        $names = $model->where("ad_id", "in", $id)->column('name');
        Utils::addOptLog($names, '', 'ads', '删除广告商');
        $res = $model->where("ad_id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }


    public function channel()
    {
        $name = $this->request->param('name/s');
        $where = [];
        if (!empty($name)) {
            $where[] = ["ac_name", "LIKE", "%{$name}%"];
        }
        [$data, $total] = $this->getDataList(new AdsChannelModel(), $where, null, 'ac_id DESC');
        foreach ($data as &$datum) {
            $datum['ac_reg_time'] = empty($datum['ac_reg_time']) ? '-' : Util::timeToStr($datum['ac_reg_time']);
            $datum['channel_url'] = ($datum['ac_url'] != '' ? $datum['ac_url'] : getAppUrl(false)) . "/#/?share_code={$datum['ac_share_code']}&acid={$datum['ac_id']}";
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'ad_type' => Utils::getOptionsByConfig(config('custom.ad_type'))
        ]);
    }


    //添加
    public function channel_add()
    {
        if ($this->request->has('add')) {
            $info = $this->request->param('info/a');
            $model = new AdsChannelModel();
            //验证数据
            try {
                validate(AdsChannelValidate::class)->check($info);
            } catch (ValidateException $e) {
                return $this->error($e->getError());
            }
            if (isset($info['ac_id']) && $info['ac_id'] > 0) {
                $old = $model->where('ac_id', $info['ac_id'])->find();
                if ($old) {
                    Utils::addOptLog($old->toArray(), $info, 'ad_channel', '修改广告渠道');
                }
            } else {
                Utils::addOptLog($info, '', 'ad_channel', '增加广告渠道');
            }
            return $this->save($model, $info, false, 'ac_id');
        }
        if ($this->request->has('find')) {
            $id = $this->request->param('ac_id');
            if (empty($id)) {
                return $this->error('数据不存在');
            }
            $model = new AdsChannelModel();
            $res = $model->where("ac_id", $id)->find();
            return $this->success(['data' => $res]);
        }
        return $this->success();
    }

    //删除
    public function channel_delete()
    {
        $id = $this->request->param('ac_id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new AdsChannelModel();
        $names = $model->where("ac_id", "in", $id)->column('ac_name');
        Utils::addOptLog($names, '', 'ad_channel', '删除广告渠道');
        $res = $model->where("ac_id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }

    public function report()
    {
        $act = $this->request->param('act/s');
        $acid = $this->request->param('acid/d');
        $date = $this->request->param('date/s');
        $export = $this->request->param('export/s');
        $channel = AdsChannelModel::where('ac_id', $acid)->find();
        if (empty($channel)) {
            return $this->error('渠道错误');
        }
        if ($act == 'update') {
            ReportService::updateAdReport($date, $acid);
            return $this->success();
        }
//        $channel_user = UserModel::where('rec_code', $channel['ac_share_code'])->find();
        $where = [];
        $date_arr = [];
        if (!empty($date)) {
            $date_arr = array_map(function ($val) {
                return date("Ymd", strtotime($val));
            }, array_filter(explode(' - ', $date)));
        }
        if (empty($date_arr) || count($date_arr) != 2) {
            $date_arr = [
                Carbon::now()->subDays(7)->format("Ymd"),
                Carbon::now()->format("Ymd"),
            ];
        }
        if ($date_arr[1] > date("Ymd")) {
            $date_arr[1] = date("Ymd");
        }
        $where[] = ['sr_acid', "=", $acid];
        $where[] = ["sr_dt", ">=", $date_arr[0]];
        $where[] = ["sr_dt", "<=", $date_arr[1]];
        [$data, $total] = $this->getDataList(Db::name('ad_report'), $where, null, 'sr_dt DESC');
        $result = [];
        foreach ($data as &$datum) {
            $result[$datum['sr_dt']] = $datum;
        }
        $field = Db::name('ad_report')->getFields();
        for ($i = $date_arr[1]; $i >= $date_arr[0]; $i--) {
            if (!isset($result[$i])) {
                $row = [];
                foreach ($field as $k => $v) {
                    $row[$k] = 0;
                }
                $row['sr_dt'] = $i;
                $row['sr_acid'] = $acid;
//                $row['sr_uid'] = $channel_user['id'];
                $row['sr_share_code'] = $channel['ac_share_code'];
                $result[$i] = $row;
            }
        }
        $list = array_values($result);

        if ($export == 1) {
            $header = [
                'sr_dt' => '日期',
                'sr_share_code' => '邀请码',
                'sr_reg' => '新注册',
                'sr_user' => '累计用户数',
                'sr_first' => '首充用户数',
                'sr_first_amount' => '首充金额',
                'sr_recharge_user' => '充值人数',
                'sr_recharge_amount' => '充值金额',
                'sr_withdraw_user' => '提现人数',
                'sr_withdraw_amount' => '提现金额',
                'sr_validbet' => '有效投注',
                'sr_user_lost' => '客损',
                'sr_bonus' => '优惠',
            ];
            $file_name = genCsv($header, $list, lang('渠道报表') . date('YmdHis', time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            Utils::addOptLogByHandle('渠道报表导出');
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['lists'] = $list;
            $result['code'] = 1;
            return Respons::run($result);
        }
        return $this->success([
            'total' => $total > count($result) ? $total : count($result),
            'data' => $list,
            'ad_type' => Utils::getOptionsByConfig(config('custom.ad_type'))
        ]);
    }

    public function channel_test()
    {
        $id = $this->request->param('ac_id/s');
        $type = $this->request->param('type/s');
        $uid = $this->request->param('uid');
        $amount = $this->request->param('amount/f');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new AdsChannelModel();
        $channel = $model->where("ac_id", $id)->find();
        if (empty($channel['ac_token'])) {
            return $this->error('token错误,无法测试', ['data' => $channel]);
        }
        if (empty($channel['ac_test_event_code'])) {
            return $this->error('test_event_code错误,无法测试', ['data' => $channel]);
        }
        PixelService::setAid($id);
        $eventId = empty($uid) ? Util::random(10) : $uid;
        $eventData = [];
        $userData = [];
        if ($type == 'first_recharge') {
            if (empty($amount)) {
                return $this->error('请输入首充金额');
            }
            $eventData = [
                'currency' => strtoupper(Util::getCurrencyDefaultSymbol('code')),
                'value' => sprintf4f($amount, 2, false)
            ];
        }
        $trace = PixelService::trace($type, $eventId, $eventData, $userData);
        return $trace ? $this->success(['data' => ['saa', strval($trace)]]) : $this->error();
    }
}