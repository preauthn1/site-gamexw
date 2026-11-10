<?php
namespace app\manage\controller;

use app\api\redis\ApiRedis;
use app\common\model\UserAgentConfigModel;
use think\Request;
use app\common\model\UserAgentModel;
use app\common\model\UserAdminLogModel;
use think\facade\View;
use think\facade\Session;
use app\common\redis\CommomRedis;
use app\manage\factory\UseragentFactory;
use app\common\lib\Respons;

class UserAgent extends Base
{	
	protected $middleware = ['ManageCheckLogin', "Language"];

	//列表
    public function list(Request $request){ 
        $rsps = (new UseragentFactory($request->token->uid))->list(true);
        return $rsps;
    }
    
    //修改资料
    public function edit(Request $request){
        $param =  $request->param();
        $info = $param['info'];
        if (!isset($info['id'])) {
            return $this->error('id不能为空');
        }
        $upd = [];
        if (!empty($info['status'])) $upd['status']=$info['status'];
        if (!empty($info['rate'])) $upd['rate']=floatval($info['rate']) / 100;

        if (empty($upd)) {
            return $this->error('参数有误');
        }
        $upd['id'] = $info['id'];
        $upd['website_auth'] = $info['website_auth']??0;

        $UserAgentModel = new UserAgentModel();
        $data = $UserAgentModel->where('id',$info['id'])->find();
        if (empty($data)) {
            return $this->error('代理信息不存在');
        }
        if ($data['proxy_level'] > 1 && isset($upd['rate'])) {
            $upuser = $UserAgentModel->where('uid', $data['proxy_upuid'])->find();
            if ($upuser && $upd['rate'] > $upuser['rate']) {
                return $this->error('下级返佣比例不能超过上级');
            }
        }
        $rs = $UserAgentModel->update($upd);
        //修改代理对应站点设置
        //查询当前用户下面是否存在该设置
        $uid = $data['uid'];
        unset($info['id']);
        $detail = UserAgentConfigModel::where('website_uid', '=', $uid)->find();
        if(!$detail){
            $detail = new UserAgentConfigModel();
            $detail->website_uid = $uid;
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
                UserAgentConfigModel::clearCache($uid, $domain);
            }
            $info['website_web_url'] = implode(',', $domainList);
        }
        $info['website_status'] = $info['website_auth'];
        $detail->save($info);

        UserAgentConfigModel::clearCache($uid, '');

        UserAdminLogModel::add_log($upd['id'], lang("代理修改") . (isset($upd['rate']) ? lang("返佣比例").":{$data['rate']}->{$upd['rate']}" : ""),Session::get("adminInfo.name"));
        return Respons::run(1,'','',$data,$info,$UserAgentModel,lang('代理修改'));
    }
    
    //编辑详情
    public function edit_detail(Request $request){
        $param = $request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("id不能为空"), 0);
        }
        $UserAgentModel =new UserAgentModel();
        $res = $UserAgentModel
                ->alias("a")
                ->Join("dq_user b","a.uid=b.id")
                ->where("a.id",$param['id'])
                ->field("a.*,b.username")
                ->find();
        
        return Respons::run(1,$res);
    }
}