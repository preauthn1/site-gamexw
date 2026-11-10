<?php
namespace app\promote\controller;

use app\manage\factory\LanguageFactory;
use app\common\model\{LangModel, LangItemsModel, LangItemDetailModel, UserAgentConfigModel, UserAgentLangItemModel};
use app\common\lib\{Respons, Uploads, Util};
use PhpOffice\PhpSpreadsheet\{IOFactory, Spreadsheet};
use think\facade\{Session, View, Config, Db};
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use think\Request;

class Language extends Base
{   

    public function items(Request $request){
        $model = new UserAgentLangItemModel();
        $where = [];
        $where[] = ['is_public', '=', 1];
        return (new LanguageFactory($this->user_id))->items($model, $where, $this->user_id);
    }

    public function translation(Request $request){
        $param = $request->param();
        if(!empty($param)){
            $item_id = $param['item_id'];
            $lang_id = $param['lang_id'];
            $text = $param['value'];
            $detail = UserAgentLangItemModel::where(['item_id'=>$item_id, 'lang_id'=>$lang_id, 'uid' => $this->user_id])->find();
            $item_name = LangItemsModel::where('id',$item_id)->value('title');
            $is_lock = LangItemsModel::where('id',$item_id)->value('is_lock');
            $lang_name = LangModel::where('id',$lang_id)->value('title');
            if(!empty($detail)){
                $da = [
                    'id'=>$detail['id'],
                    'item_id'=>$item_name.' ',
                    'lang_id'=>$lang_name.' ',
                    'text'=>$detail['text'],
                ];
                $editid = UserAgentLangItemModel::where(['item_id'=>$item_id, 'lang_id'=>$lang_id, 'uid' => $this->user_id])->update(['text'=>$text]);
                $da2 = [
                    'id'=>$detail['id'],
                    'item_id'=>$item_name,
                    'lang_id'=>$lang_name,
                    'text'=>$text,
                    'uid'=>$this->user_id,
                ];
//                (new \app\api\redis\ApiRedis())->set("updateLangTime", time(), 90);
                return Respons::run(1,'','',$da,$da2,new UserAgentLangItemModel(),lang('代理语言类目子类目内容修改'));
            }else{
                $addid = UserAgentLangItemModel::insertGetId(['item_id'=>$item_id, 'lang_id'=>$lang_id,'text'=>$text, 'uid' => $this->user_id]);
                $da = [
                    'id'=>$addid,
                    'item_id'=>$item_name,
                    'lang_id'=>$lang_name,
                    'text'=>$text,
                    'uid'=>$this->user_id,
                ];
//                (new \app\api\redis\ApiRedis())->set("updateLangTime", time(), 90);
                return Respons::run(1,'','','',$da,new UserAgentLangItemModel(),lang('代理语言类目子类目内容添加'));
            }
        }else{
            return Respons::run(0);
        }
    }

    //导出数据
    public function excel(Request $request){
        $langs = LangModel::where('status', 1)->order('id asc')->select();
        $sql = "SELECT ";
        foreach ($langs as $v){
            $sql .= "MAX(CASE WHEN a.lang = '{$v['lang']}' THEN c.text END) AS {$v['lang']}_text,";
        }
        $sql .= " c.item_id, b.title FROM dq_lang a
CROSS JOIN dq_lang_items b
JOIN dq_user_agent_website_lang_item c ON a.id = c.lang_id AND b.id = c.item_id AND c.uid = {$this->user_id}
GROUP BY c.item_id, b.title ORDER BY c.item_id DESC";
        $messages = Db::query($sql);
        $letter = ["B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L"];
        
        // 实例化
        $spreadsheet = new Spreadsheet();
        
        // 获取活动单元格
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getCell('A1')->setValue(lang('类目'));
        foreach($langs as $key => $val){
            $sheet->getCell($letter[$key].'1')->setValue($val['title']);
        }
        
        foreach($messages as $mk => $vo){
            $n = $mk + 2;        
            $sheet->getCell('A' . $n)->setValue($vo['title']);
            foreach($langs as $key => $val){
                $sheet->getCell($letter[$key] . $n)->setValue($vo["{$val['lang']}_text"]);
            }
        }
        $save_path = public_path();
        $name = Util::random(10);
        $file_name = "storage/xlsx/{$name}.xlsx";
        // 实例化导出类
//        header('Content-Type:application/vnd.ms-excel');
//        header('Content-Disposition:attachment;filename=' . $file_name);
//        header('Cache-Control:max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($save_path . $file_name);
        $s3_url = Uploads::uploadS3($save_path . $file_name);
        return Respons::run(1,[
            'url' => $s3_url ?: toMedia($file_name, '/')
        ],'','','',new LangModel(),lang('语言类目数据导出'));
    }
    
    public function import(Request $request){
        if(!empty($_FILES)) {
            $files = Uploads::upload_excel();
            if ($files['code'] == 0) {
                return Respons::run($files);
            }
            $file = $files['data']['file'];
        } else {
            $file = $request->param('file/s');
        }
        if (!file_exists(toMedia($file, '', 2))) {
            return Respons::run(0, '', '请上传文件');
        }
        $objReader = IOFactory::createReader('Xlsx');
        $ids = [];
        try{
            $objPHPExcel = $objReader->load($file);
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $title = $items = [];
            $arr = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O');
            foreach($arr as $k => $currentColumn){
                $title[] = $objPHPExcel->getActiveSheet()->getCell($currentColumn . "1")->getValue();
            }
            $title = array_filter($title);
            $category = [];
            for ($i = 2; $i <= $highestRow; $i++) {
                foreach($title as $k => $v){
                    if($v === "类目"){
                        $category[] = (string)$objPHPExcel->getActiveSheet()->getCell($arr[$k] . $i)->getValue();
                    }else{
                        $items[$k][] = (string)$objPHPExcel->getActiveSheet()->getCell($arr[$k] . $i)->getValue();
                    }
                }
            }

            /* 获取语言种类Id*/
            $lang = [];
            foreach($title as $k => $v){
                $langId = LangModel::where(['title' => $v])->value('id');
                if(empty($langId)){
                    $lang[$k] = '';
                }else{
                    $lang[$k] = $langId;
                }
            }
            if(!empty($category)){
                foreach($category as $key => $val){
                    $itemId = LangItemsModel::where(['title'=>$val])->value('id');
                    $is_lock = 0;
                    if(empty($itemId)){
                       $itemId = LangItemsModel::insertGetId(['title' => $val]);
                    } else {
                        $is_lock = LangItemsModel::where(['id'=>$itemId])->value('is_lock');
                    }
                    if($is_lock == 0){
                        foreach($items as $k => $v){
                            $langId = $lang[$k];
                            if($langId){
                                $text = $v[$key];
                                if(empty($text)){
                                    $text = '';
                                }
                                $detailId = UserAgentLangItemModel::where(['lang_id'=>$langId, 'item_id'=>$itemId, 'uid' => $this->user_id])->value('id');
                                if(!empty($detailId)){
                                    UserAgentLangItemModel::where(['item_id'=>$itemId, 'lang_id'=>$langId])->update(['text'=>$text]);
                                    $ids[] = $detailId;
                                }else{
                                    $addid = UserAgentLangItemModel::insertGetId(['item_id'=>$itemId, 'lang_id'=>$langId,'text'=>$text, 'uid' => $this->user_id]);
                                    $ids[] = $addid;
                                }
                            }
                        }
                    }
                }
            }
            $ids['id'] = empty($ids) ? '':implode(',',$ids);
            Respons::run(1,'','','',$ids,new UserAgentLangItemModel(),lang('代理语言类目导入'));
        } catch (\Exception $t){
            return json(["code" => 0, 'msg' => $t->getMessage()]);
        }
        return json(["code" => 1, 'msg' => '上传成功']);
    }

    public function update(Request $request){
        $param = $request->param();
        try {
            $this->reWriteLang($param);
            return Respons::run(1,'','','','',new LangItemDetailModel(),lang('代理更新语言包'));
        } catch (\Exception $e) {
            return Respons::run(0, [], $e->getMessage());
        }

//        if(!empty($param)){
//            $this->reWriteLang($param['id']);
//        } else {
//            $this->reWriteLang();
//        }

    }

    private function reWriteLang($param = '') {
        //获取网站对应域名列表
        $condition = [];
        $condition[] = ['website_uid', '=', $this->user_id];
        $condition[] = ['website_status', '=', 1];
        $webDomain = UserAgentConfigModel::where($condition)->value('website_web_url');
        if( !$webDomain ) {
            return true;
        }
        $lang_config = [];
        $lang_arr = [];
        (new LangModel())
            ->where(['status' => 1])
            ->when(isset($param['id']), function($query) use($param) {
                $query->where(['id' => $param['id']]);
            })
            ->filter(function($info) use(&$lang_arr){
                $lang_arr[$info->id] = strtolower($info->lang);
            })
            ->order('is_default DESC, id ASC')
            ->field('lang,id')
            ->select();
        foreach($lang_arr as $k=>$v){
            (new LangItemsModel())
                ->with(['getTextAgent' => function($query) use($k){
                    $query->where(['lang_id'=>$k]);
                }, 'getText' => function($query) use($k){
                    $query->where(['lang_id'=>$k]);
                }])
                ->distinct(true)
                ->chunk(1000,function($data) use(&$lang_config,$v){
                    foreach($data as $val){
                        $lang_config[$v][$val->title] = $val->getTextAgent->text ?? $val->text;
                    }
                },'id','desc');
        }
        $domainList = explode(',', $webDomain);
        foreach ($domainList as $domain){
            $_domain = strtolower(parse_url($domain)['host']);
            foreach ($lang_config as $lang => $config) {
                $config = myArrayFilter($config);
                if( empty($config) ){
                    continue;
                }
                $lang_file = base_path("lang") . "{$_domain}_{$lang}.php";
                $code = "<?php \r\nreturn " . var_export($config, true) . ";";
                $handle = fopen($lang_file, 'w');
                fwrite($handle, $code);
                fclose($handle);
            }
            UserAgentConfigModel::clearCache($this->user_id, $_domain);
        }

    }
}
