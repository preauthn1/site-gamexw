<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\model\LangItemDetailModel;
use app\common\model\LangItemsModel;
use app\common\model\LangModel;
use think\facade\Request;
use think\facade\Config;

class LanguageFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function items($model = null, $where1 = [], $uid = 0)
    {
        $isAgent = true;
        if( !$model ){
            $model = new LangItemDetailModel();
            $isAgent = false;
        }
        $param = Request::instance()->param();
        $param = Request::instance()->param();
        $limit = $param['limit'] ?? 10;
        $where = [];
        if(isset($param['text']) && !empty($param['text'])){
            $w[] = ['text','like','%'.$param['text'].'%'];
            $text_id = $model->where($w)->group('item_id')->column('item_id');
            $where[] = ['id','in',$text_id];
        }
        if(isset($param['title']) && !empty($param['title'])){
            $where[] = ['title','like','%'.$param['title'].'%'];
        }
        if(isset($param['is_public']) && !empty($param['is_public'])){
            $where[] = ['is_public','=',$param['is_public']];
        }
        $items = LangItemsModel::order('id desc')->where($where)->where($where1)->paginate($limit,false,['query' => $param]);
        $langs = LangModel::where('status', 1)->order('id asc')->select();
        foreach ($items as $key => $val){
            $data = [];
            foreach ($langs as $k => $v){
                $wh = [];
                $wh['lang_id'] = $v['id'];
                $wh['item_id'] = $val['id'];
                if( $isAgent ){
                    $wh['uid'] = $uid;
                }
                $text = $model->where($wh)->find();
                //如果是代理用户没有，则查询总后台设置的
                if( $isAgent && (!$text || !$text['text']) ){
                    $wh = [];
                    $wh['lang_id'] = $v['id'];
                    $wh['item_id'] = $val['id'];
                    $text = LangItemDetailModel::where($wh)->find();
                }
                $data[] = ['text'=>$text ? $text['text'] : '', 'lang_id'=>$v['id']];
                $val["lang_text_{$v['id']}"] = $text ? $text['text'] : '';
            }
            $val['detail'] = $data;
            $items[$key] = $val;
        }
        $info['data'] = $items;
        $info['langs'] = $langs;
        $info['code'] = 1;
        return Respons::run($info);
    }

    public function index()
    {
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $rsps['count']= LangModel::where($where)->count();
        $rsps['data']= LangModel::where($where)->limit((($page-1)*$limit),$limit)->order("sort desc")->select();
        $rsps['code']=1;
        $rsps['currency'] = Config::get('custom.country');
        return Respons::run($rsps);
    }
}