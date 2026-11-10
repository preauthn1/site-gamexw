<?php

namespace app\promote\controller;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\UserAgentConfigModel;
use app\model\SysSets;
use think\Request;

class System extends Base{

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if(isset($param['edit'])){
            $info = $param['info'];
            //查询当前用户下面是否存在该设置
            $detail = UserAgentConfigModel::where('website_uid', '=', $this->user_id)->find();
            if(!$detail){
                $detail = new UserAgentConfigModel();
                $detail->website_uid = $this->user_id;
            }
//            if( !$info['website_web_url'] ){
//                return Respons::run(0, [], '至少绑定一个域名');
//            }
            if( $info['website_web_url'] ){
                //域名不能和主域名一致
                $sys = (new ApiRedis())->systemInfo();
                if( !isset($sys['web_url']) ){
                    return Respons::run(0, [], '后台必须设置主域名');
                }
                $domainList = explode(',', $info['website_web_url']);
                foreach ($domainList as &$domain){
                    $domain = str_replace('http://', 'https://', $domain);
                    $_domain = parse_url($domain);
                    $_domain = $_domain['host'] ?? $_domain['path'];
                    if( strtolower($_domain) == strtolower($sys['web_url']) ){
                        return Respons::run(0, [], '域名不能包含主域名');
                    }
                }
                $info['website_web_url'] = implode(',', $domainList);
            }

            $detail->save($info);
            return Respons::run(1);
        }
        if (isset($param['find'])) {
            //查看当前用户下面设置的站点数据
            $detail = UserAgentConfigModel::where('website_uid', '=', $this->user_id)->find();
            if( !$detail ){
                //没有查看系统默认的
                $detail = $this->getSystemDefault();
                $detail['website_status'] = 0;
            }
            return Respons::run(1, $detail);
        }

    }

    //获取系统默认的站点数据
    private function getSystemDefault(){
        return SysSets::where('set_label', '=', '站点配置')->column('set_value', 'set_code');
    }

}