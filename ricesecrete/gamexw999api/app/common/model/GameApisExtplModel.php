<?php
namespace app\common\model;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Utils;
use app\model\GameBetsex;
use think\facade\Db;
use think\facade\Validate;
use think\Model;
use think\Exception;

class GameApisExtplModel extends Model
{
    protected $name = 'game_apis_extpl';
    protected $pk = 'gae_id';


    //改了TIPS规则，此方法废弃
    public static function clearTips(&$arrdata,$data){
        if (!$arrdata) return;
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                self::clearTips($arrdata, $v);
            } else {
                $tags=[];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tags);
                foreach ($tags[0] as $tag) {
                    $tag=substr($tag, 1, -1);
                    $tag=explode("|",$tag);
                    $tag=$tag[count($tag)-1];
                    unset($arrdata[$tag],$arrdata[$tag.'tip']);
                }
            }
        }
    }

    /** 清洁模板及验证数据，用于替换标签数据
     * @param array $data 模板数据（可能包含指定GID的和GID为0的数据）
     * @param array $formDatas 表单数据，用于验证
     * @return array|true 返回true表示验证通过，返回数组表示验证不通过
     */
    public static function procClearTemps(&$data,$formDatas){
        //处理get
        if (!empty($data['gae_param_gettpl'])){
            if (is_string($data['gae_param_gettpl'])) {
                $params=[];
                $data['gae_param_gettpl'] = parse_str($data['gae_param_gettpl'],$params);
            }
            foreach ($params as $k => $v) {
                $tags=[];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tags);
                foreach ($tags[0] as $tag) {
                    $tag=substr($tag, 1, -1);
                    $tag=explode("|",$tag);
                    $tag=$tag[count($tag)-1];
                    unset($params[$tag],$params[$tag.'tip']);
                }
            }
            $data['gae_param_gettpl'] = urldecode(http_build_query($params));
        }
        //处理post
        if (!empty($data['gae_param_posttpl'])){
            if (is_string($data['gae_param_posttpl'])) $data['gae_param_posttpl'] = json_decode($data['gae_param_posttpl'],true);
            if (isset($data['gae_param_posttpl']['apitpl'])) $data['gae_param_posttpl'] = $data['gae_param_posttpl']['apitpl'];
            //self::clearTips($data['gae_param_posttpl'],$data['gae_param_posttpl']);
        }
        //处理head
        if (!empty($data['gae_param_headtpl'])){
            if (is_string($data['gae_param_headtpl'])) $data['gae_param_headtpl'] = json_decode($data['gae_param_headtpl'],true);
            if (isset($data['gae_param_headtpl']['apitpl'])) $data['gae_param_posttpl'] = $data['gae_param_headtpl']['apitpl'];
            //self::clearTips($data['gae_param_headtpl'],$data['gae_param_headtpl']);
        }
        //验证数据
        if (!empty($data['gae_cfg'])){
            $cfg=json_decode($data['gae_cfg'],true);
            if (isset($cfg['validate'])){
                $cfg=$cfg['validate'];
                $v=Validate::message($cfg['message'])->batch();
                if (!$v->check($formDatas,$cfg['rule'])){
                    $error=$v->getError();
                    if (is_array($error)) $error=implode(",",$error);
                    return ['code'=>0,'msg'=>$error];
                }
            }
        }
        return true;
    }

    public static function getTips(&$fromitems,&$arrdata,$data){
        if (!$arrdata) return;
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                self::getTips($fromitems,$arrdata, $v);
            } else {
                $tags=[];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tags);
                foreach ($tags[0] as $tag) {

                    $tag=substr($tag, 1, -1);
                    $tag=explode("|",$tag);
                    $type=count($tag)>1?$tag[0]:'string';
                    $tag=$tag[count($tag)-1];
                    if (in_array($tag,array_merge(array_keys(array_merge(Meta::UFIELD,Meta::UFIELD,Meta::GFIELD,Meta::TFIELD)),Meta::SFIELD))) continue;
                    $fromitems[$tag]=[
                        'title'=>(isset($arrdata['tips']) && isset($arrdata['tips'][$tag]))?$arrdata['tips'][$tag]:$tag,
                        'tip'=>(isset($arrdata['tips']) && isset($arrdata['tips'][$tag.'tip']))?$arrdata['tips'][$tag.'tip']:'',
                        'type'=>$type
                    ];
                }
            }
        }
    }


    /**获取带标签的填写模板
     * @param $data
     * @return array (field=>[title,tip,type]) 其中title是表单项标题，tip是表单项提示，type是表单项类型， type为Meta::parseMateArrData中支持的type一致
     */
    public static function getFormItems($data){
        //print_r($data);
        $formItems=[];
        //处理get
        if (!empty($data['gae_param_gettpl'])){
            if (is_string($data['gae_param_gettpl'])) {
                $params=[];
                $data['gae_param_gettpl'] = parse_str($data['gae_param_gettpl'],$params);
            }
            foreach ($params as $k => $v) {
                $tags=[];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tags);
                foreach ($tags[0] as $tag) {
                    $tag=substr($tag, 1, -1);
                    $tag=explode("|",$tag);
                    $type=count($tag)>1?$tag[0]:'string';
                    $tag=$tag[count($tag)-1];
                    //排除元数据标签
                    if (in_array($tag,array_merge(array_keys(array_merge(Meta::UFIELD,Meta::UFIELD,Meta::GFIELD,Meta::TFIELD)),Meta::SFIELD))) continue;
                    $formItems[$tag]=[
                        'title'=>isset($params[$tag])?$params[$tag]:$tag,
                        'tip'=>isset($params[$tag.'tip'])?$params[$tag.'tip']:'',
                        'type'=>$type
                    ];
                }
            }
        }

        //处理post
        if (!empty($data['gae_param_posttpl'])){
            if (is_string($data['gae_param_posttpl'])) $data['gae_param_posttpl'] = json_decode($data['gae_param_posttpl'],true);
            //print_r($data);
            self::getTips($formItems,$data['gae_param_posttpl'],$data['gae_param_posttpl']);
        }
        //处理head
        if (!empty($data['gae_param_headtpl'])){
            if (is_string($data['gae_param_headtpl'])) $data['gae_param_headtpl'] = json_decode($data['gae_param_headtpl'],true);
            self::getTips($formItems,$data['gae_param_headtpl'],$data['gae_param_headtpl']);
        }
        if (isset($data['gae_forminfo'])){
            $formItems['forminfo']=$data['gae_forminfo'];
        }
        $formItems['apicfg']=empty($data['gae_cfg'])?null:json_decode($data['gae_cfg'],true);
        //print_r($data);
        return $formItems;
    }

    
}