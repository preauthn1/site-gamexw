<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\AffiliateModel;
use app\common\model\AffiliatePlatformModel;
use app\common\model\LangModel;
use app\common\model\NewsModel;
use think\Request;

/**
 * 附属机构
 * Class Affiliate
 * @package app\manage\controller
 */
class Affiliate
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $AffiliateModel = new AffiliateModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $AffiliateModel->where('lang', '=', $info['lang'])->find();
            if (!empty($info['share_platform'])) {
                $info['share_platform'] = json_encode($info['share_platform']);
            }
            if (!empty($info['issues_list'])) {
                $info['issues_list'] = json_encode($info['issues_list']);
            }
            if (empty($data)) {
                unset($info['id']);
                $res = $AffiliateModel->insertGetId($info);
                return Respons::run($res ? 1 : 0, '', '', $data, $info, $AffiliateModel, lang('修改'));
            } else {
                $AffiliateModel->where('id', $data['id'])->update($info);
                return Respons::run(1, '', '', $data, $info, $AffiliateModel, lang('修改'));
            }
        }
        if (isset($param['find'])) {
            $langs = LangModel::where('lang', '<>', '')->where('status',1)->order('id asc')->select();
            $lang_arr = [];
            foreach ($langs as $value) {
                $lang_arr[] = [
                    'label' => $value['title'],
                    'value' => $value['lang']
                ];
            }
            if (!empty($param['lang'])) {
                $lang = $param['lang'];
            } else {
                $lang = $lang_arr[0]['value'];
            }
            $data = $AffiliateModel->where('lang', $lang)->find();
            if (empty($data)) {
                $data = [
                    'id' => '',
                    'lang' => '',
                    'today_tip' => '',
                    'all_tip' => '',
                    'team_url' => '',
                    'share_title' => '',
                    'share_platform' => '',
                    'share_intro' => '',
                    'service_title' => '',
                    'service_info' => '',
                    'service_tip' => '',
                    'service_contact' => '',
                    'service_platform' => '',
                    'issues_list' => '',
                    'rule_title' => '',
                    'rule_info' => '',
                    'brokerage_min' => 0,
                    'brokerage_max' => 0,
                    'brokerage_ratio' => 0,
                    'pop_info' => '',
                    'pop_explain' => '',
                    'calculate_title' => '',
                    'calculate_text' => '',
                ];
            }
            $where = [];
            $where[] = ["type", "=", 0];
            if (!empty($lang)) {
                $where[] = ["lang", "=", $lang];
            }
            $news_list = NewsModel::where($where)->limit(30)->field('id,lang,news_title,news_content')->select();
            $issues_list_arr = [];
            foreach ($news_list as $value) {
                $issues_list_arr[] = [
                    'label' => $value['news_title'],
                    'value' => '' . $value['id']
                ];
            }
            $platform_arr = AffiliatePlatformModel::where('id', '>', 0)->field('id,name')->select();
            $platform_list_arr = [];
            foreach ($platform_arr as $value) {
                $platform_list_arr[] = [
                    'label' => $value['name'],
                    'value' => '' . $value['id']
                ];
            }
            $data['issues_list_arr'] = $issues_list_arr;
            $data['platform_list_arr'] = $platform_list_arr;
            $data['langs'] = $lang_arr;
            if (empty($data['lang'])) {
                $data['lang'] = $lang;
            }
            if (!empty($data['share_platform'])) {
                $data['share_platform'] = json_decode($data['share_platform'], true);
            }
            if (!empty($data['issues_list'])) {
                $data['issues_list'] = json_decode($data['issues_list'], true);
            }
            return Respons::run(1, $data);
        }
    }
}