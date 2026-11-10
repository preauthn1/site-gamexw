<?php
namespace app\manage\factory;
use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\service\MemberService;
use app\common\Utils;
use think\facade\Config;
use think\facade\Db;
use app\common\model\{UserAgentConfigModel, UserAgentModel, UserLevelModel, UserModel};
use think\facade\Request;
class UseragentFactory  {

    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list($webSite = false) {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["a.uid|b.phone|rec_code", "=", $param['username']];
            } else {
                $where[]=["b.username|b.mail|b.phone", "=", $param['username']];
            }
        }
        if (isset($param['rec_id']) && $param['rec_id']!='') {
            //查询rec_id
            $sqlwhere = [];
            if (is_numeric($param['rec_id'])) {
                $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
            } else {
                $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
            }
            $rec_info = UserModel::where($sqlwhere)->find();
            if (empty($rec_info)) {
                $result['code'] = 0;
                $result['msg'] = '上级UID或邀请码有误';
                return Respons::run($result);
            }
            if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
            } else {
                $where[]=["a.rec_id", "=", $rec_info['id']];
            }
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=",$param['status']];
        }
        if (isset($param['website_auth']) && $param['website_auth']!='') {
            $where[]=["a.website_auth","=",$param['website_auth']];
        }
        if (isset($param['type']) && $param['type']!='') {
            $where[]=["a.type","=",$param['type']];
        }
        if (isset($param['domain']) && $param['domain']!='') {
            $where[]=["c.website_web_url","like", '%'.$param['domain'].'%'];
        }

        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportList($where, '');
        }
        $user_level_data = UserLevelModel::column('level_name', 'id');
        $UserAgentModel =new UserAgentModel();
        $where[] = ['b.rec_id','=',0];
        $where[] = ['a.is_del','=',0];
        $rsps['data']=$UserAgentModel
                ->alias("a")
                ->Join("dq_user b","a.uid=b.id")
                ->join('dq_user_agent_website_config c', 'c.website_uid=a.uid', 'left')
                ->where($where)
                ->order("a.id desc")
                ->field("a.*,b.username,b.lebal,b.rec_code,b.rec_click,b.rec_id,b.lebal,b.level_id,b.phone,b.qh,b.mail,c.website_web_url as domain,"
                        ."(select username from dq_user where id=b.rec_id ) as rec_name")
                ->paginate($limit,false,['query' => $param]);
        $country = Config::get('custom.country');

        foreach($rsps['data'] as $key => $value){
            $value = $value->toArray();
            $value['level_name'] = $user_level_data[$value['level_id']];
            $value['apply_time'] = date("Y-m-d H:i:s",$value['apply_time']);
            $value['rate'] = round(floatval($value['rate']) * 100, 2);
            if( $webSite ){
                //查询站点权限
                $hidden = ['id', 'website_status', 'create_time', 'create_time'];
                $_detail = UserAgentConfigModel::where('website_uid', '=', $value['uid'])->find();
                if( $_detail ){
                    $_detail = $_detail->toArray();
                    $_detail = hidden_array_data($_detail, $hidden);
                }else{
                    $_detail = [];
                    foreach (UserAgentConfigModel::getTableFields() as $v){
                        if( !in_array($v, $hidden) ){
                            $_detail[$v] = '';
                        }
                    }
                }
                $value = array_merge($value, $_detail);

            }
            $value['tags'] = MemberService::getTagByUid($value['uid']);
            $value = Utils::hideUserInfo($value);
            $value['country'] = $country[$value['country']] ?? '无';
            $rsps['data'][$key] = $value;
        }
        $rsps['code']=1;
        return Respons::run($rsps);
        // View::assign('is_back',isset($param['is_back'])?$param['is_back']:1);
        // return View::fetch('user_agent/list');
    }
    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportList($where = null, $whereStr = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.id';
        $params['positionOrder'] = 'DESC';
        $params['muid']=$this->uid;
        $statusArr = [
            1=>'待审核',
            2=>'已审核',
            3=>'已通过',
        ];
        $websiteArr = [
            0=>'关闭',
            1=>'开启',
        ];
        $rlt = ReportExport::export(new rptParams("代理列表",
            [
                new rptCol('uid', 'UID'),
                new rptCol('phone','用户名','app\common\service\ReportDownloadService::getUserName'),
                new rptCol('lebal','用户标签','app\common\service\ReportDownloadService::getLebal'),
                'rec_code:邀请码',
                new rptCol('level_id','会员等级','app\common\service\ReportDownloadService::getUserLevel'),
                'rec_id:上级代理',
                new rptCol('type','代理类型','app\common\service\ReportDownloadService::getAgencyType'),
                new rptCol('rate','返佣比例','app\common\service\ReportDownloadService::getAgencyRate'),
                new rptCol('apply_time', '注册时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                'apply_ip:注册ip',
                new rptCol('status','状态',rptCol::transType_kvtrans,$statusArr),
                new rptCol('website_auth','站点权限',rptCol::transType_kvtrans,$websiteArr),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['level_id', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $UserAgentModel =new UserAgentModel();
        return $UserAgentModel->alias("a")->where($param['where'])->where($param['whereStr'])
            ->join("dq_user b","a.uid=b.id")
            ->field("a.*,b.username,b.lebal,b.rec_code,b.rec_id,b.lebal,b.level_id,b.phone,b.qh,b.mail,"
                ."(select username from dq_user where id=b.rec_id ) as rec_name");
    }
}
?>