<?php

namespace app\manage\controller;

use app\common\service\GoogleTranslate;
use app\manage\factory\LanguageFactory;
use app\common\model\{LangModel, LangItemsModel, LangItemDetailModel};
use app\common\lib\{Respons, Uploads, Util};
use PhpOffice\PhpSpreadsheet\{IOFactory, Spreadsheet};
use think\facade\{Session, View, Config, Db};
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use think\Request;

class Language
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    public function __construct(LangModel $LangModel, LangItemsModel $LangItemsModel)
    {
        $this->LangModel = $LangModel;
        $this->LangItemsModel = $LangItemsModel;
    }


    public function index(\think\Request $request)
    {
        $rsps = (new LanguageFactory($request->token->uid))->index();
        return $rsps;
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $country = Config::get('custom.country');
        if (isset($param['edit'])) {
            $info = $param['info'];
            $upd['id'] = $info['id'];
            $upd['title'] = $country[$info['lang']];
            $upd['lang'] = $info['lang'];
            $upd['is_default'] = $info['is_default'];
            $upd['status'] = $info['status'];
            $upd['web_status'] = $info['web_status'];
            $upd['flag'] = $info['flag'];
            $upd['sort'] = $info['sort'];
            if (isset($upd['is_default']) && $upd['is_default'] == 1) {  //确保开启默认的时候 该语言是开启的
                $upd['status'] = 1;
                $upd['web_status'] = 1;
            }
            try {
                $this->setDefa($upd); //检验是否唯一默认语言
                [$model, $data] = getOneData($this->LangModel, ['id' => $info['id']]);
                $res = $model->allowField(['title', 'lang', 'is_default', 'status', 'web_status', 'flag', 'sort'])->save($upd);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0, '', '', $data, $upd, $this->LangModel, lang('语言种类修改'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        if (isset($param['find'])) {
            $res = LangModel::where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        $country = Config::get('custom.country');
        if (isset($param['add'])) {
            $info = $param['info'];
            $info['title'] = $country[$info['lang']] ?? lang('中国');
            $info['lang'] = !empty($info['lang']) ? $info['lang'] : 'CN';
            try {
                $this->setDefa($info); //检验是否唯一默认语言
                $res = $this->LangModel->save($info);
                Db::commit();
                $info['id'] = $this->LangModel->id;
                $this->setRedis();
                return Respons::run($res ? 1 : 0, '', '', '', $info, $this->LangModel, lang('语言种类添加'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }

        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            try {
                [$model, $data] = getOneData($this->LangModel, ['id' => $id]);
                if ($data['is_default'] == 1) {
                    return Respons::run(0, [], lang('不允许删除默认语言'));
                }
                $res = $model->delete();
                LangItemDetailModel::where(['lang_id' => $id])->delete();
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0, '', '', $data, '', $this->LangModel, lang('语言种类删除'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }

        }
    }

    public function items(\think\Request $request)
    {
        $rsps = (new LanguageFactory($request->token->uid))->items();
        return $rsps;
//        if(isset($param['title'])){
//            $info['data'] = LangModel::order('id asc')->select();
//            $info['code'] = 1;
//            return Respons::run($info);
//        }
//        return View::fetch('language/items');
    }


    public function delitem(Request $request)
    {
        $param = $request->param();
        $id = intval($param['id']);
        $delnews = LangItemsModel::where(['id' => $id])->delete();
        if ($delnews) {
            LangItemDetailModel::where(['item_id' => $id])->delete();
            return Respons::run(1, '', '', $param, '', new LangItemsModel(), lang('语言类目删除'));
        } else {
            return Respons::run(0);
        }

    }

    public function edititem(Request $request)
    {
        $param = $request->param();
        if (!empty($param)) {
            $data = $param;
            if (isset($data['is_lock'])) {
//                $admin = Session::get("adminInfo");
//                if(($admin['username'] != "language" && $admin['username'] != 'manage') && $data['is_lock'] == 0){
//                    return Respons::run(0, "", "无权限解锁");
//                }
                $ret = LangItemsModel::where(['id' => $data['id']])->update(['is_lock' => $data['is_lock'] == 0 ? 0 : 1]);
                $code = $ret !== false ? 1 : 0;
                return Respons::run($code);
            } else if (isset($data['is_public'])) {
                $wh['title'] = trim($data['title']);
                $lang_arr = LangItemsModel::where($wh)->find();
                if (isset($lang_arr)) {
                    return Respons::run(0, [], lang('类目已存在'));
                }
                if (isset($data['id']) && !empty($data['id'])) {
                    $ret = LangItemsModel::where(['id' => $data['id']])->update(['is_public' => $data['is_public'] == 0 ? 0 : 1]);
                } else {
                    $ret = LangItemsModel::insertGetId($data);
                }
                $code = $ret !== false ? 1 : 0;
                return Respons::run($code);
            } else {
                $wh['title'] = trim($data['title']);
                $lang_arr = LangItemsModel::where($wh)->find();
                if (isset($lang_arr)) {
                    return Respons::run(0, [], lang('类目已存在'));
                }
                if (isset($data['id']) && !empty($data['id'])) {//编辑
                    $id = $data['id'];
                    unset($data['id']);
                    $info = LangItemsModel::where('id', $id)->find();
                    $editid = LangItemsModel::where(['id' => $id])->update($data);
                    $data['id'] = $id;
                    return Respons::run($editid ? 1 : 0, '', '', $info, $data, new LangItemsModel(), lang('语言类目修改'));
                } else { //增加
                    unset($data['id']);
                    $addid = LangItemsModel::insertGetId($data);
                    $data['id'] = $addid;
                    return Respons::run($addid ? 1 : 0, '', '', '', $data, new LangItemsModel(), lang('语言类目添加'));

                }
            }
        }
    }

    public function setstatus(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            if (isset($info['is_default']) && $info['is_default'] == 1) {  //确保开启默认的时候 该语言是开启的
                $info['status'] = 1;
                $info['web_status'] = 1;
            }
            Db::startTrans();
            try {
                $this->setDefa($info); //检验是否唯一默认语言
                $arr_key = implode(',', array_keys($info));
                [$model, $data] = getOneData($this->LangModel, ['id' => $info['id']], $arr_key);
                $res = $model->save($info);
                Db::commit();
                $this->setRedis();
                return Respons::run($res ? 1 : 0, '', '', $data, $info, $this->LangModel, lang('语言状态修改'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
    }

    /**
     * 数据库中 只能存在一个 默认语言 并且必须存在默认语言
     * @param $info
     */
    public function setDefa($info)
    {
        if (!isset($info['is_default'])) {
            return false;
        }
        $id = $this->LangModel
            ->where(['is_default' => 1]) //直接查询有没有默认语言
            ->when(isset($info['id']), function ($query) use ($info) { //如果没传id 就是添加操作  传了id 就是编辑的操作
                $query->where('id', 'notIn', $info['id']);
            })->value('id');
        if ($id && $info['is_default'] == 1) {
            $this->LangModel->where(['is_default' => 1])->update(['is_default' => 0]);
        }
        if ($info['is_default'] == 0 && !isset($id)) { //无论添加编辑 如果系统没有默认币种  就会提示
            throw new \think\Exception(lang('必须存在一个默认语言'), 0);
        }

    }

    public function translation(Request $request)
    {
        $param = $request->param();
        if (!empty($param)) {
            $admin = Session::get("adminInfo");
            if (isset($param['type'])) {
                if ($param['type'] == 'translate') {
                    unset($param['type']);
                    $item_id = $param['item_id'];
                    $item_name = LangItemsModel::where('id', $item_id)->value('title');
                    $langs = LangModel::where('status', 1)->order('id asc')->select();
                    foreach ($langs as $k => $v) {
                        $wh = [];
                        $wh['lang_id'] = $v['id'];
                        $wh['item_id'] = $item_id;
                        $text = GoogleTranslate::translate('zh-CN', $v['lang'], $item_name);
                        $item_detail = LangItemDetailModel::where($wh)->find();
                        if (empty($item_detail)) {
                            $da = [
                                'item_id' => $item_id,
                                'lang_id' => $v['id'],
                                'text' => $text,
                            ];
                            LangItemDetailModel::insertGetId($da);
                        } else {
                            LangItemDetailModel::where(['item_id' => $item_id, 'lang_id' => $v['id']])->update(['text' => $text]);
                        }
                    }
                    return Respons::run(1);
                }
            }
            $item_id = $param['item_id'];
            $lang_id = $param['lang_id'];
            $text = $param['value'];
            $detail = LangItemDetailModel::where(['item_id' => $item_id, 'lang_id' => $lang_id])->find();
            $item_name = LangItemsModel::where('id', $item_id)->value('title');
            $is_lock = LangItemsModel::where('id', $item_id)->value('is_lock');
            $lang_name = LangModel::where('id', $lang_id)->value('title');
            if (($admin['username'] != "language" && $admin['username'] != 'manage') && $is_lock == 1) {
                return Respons::run(0, "", "该语言无法修改，请联系管理员");
            }
            if (!empty($detail)) {
                $da = [
                    'id' => $detail['id'],
                    'item_id' => $item_name . ' ',
                    'lang_id' => $lang_name . ' ',
                    'text' => $detail['text'],
                ];
                $editid = LangItemDetailModel::where(['item_id' => $item_id, 'lang_id' => $lang_id])->update(['text' => $text]);
                $da2 = [
                    'id' => $detail['id'],
                    'item_id' => $item_name,
                    'lang_id' => $lang_name,
                    'text' => $text
                ];
                (new \app\api\redis\ApiRedis())->set("updateLangTime", time(), 90);
                return Respons::run($editid ? 1 : 0, '', '', $da, $da2, new LangItemDetailModel(), lang('语言类目子类目内容修改'));
            } else {
                $addid = LangItemDetailModel::insertGetId(['item_id' => $item_id, 'lang_id' => $lang_id, 'text' => $text]);
                $da = [
                    'id' => $addid,
                    'item_id' => $item_name,
                    'lang_id' => $lang_name,
                    'text' => $text
                ];
                (new \app\api\redis\ApiRedis())->set("updateLangTime", time(), 90);
                return Respons::run($addid ? 1 : 0, '', '', '', $da, new LangItemDetailModel(), lang('语言类目子类目内容添加'));
            }
        } else {
            return Respons::run(0);
        }
    }

    public function update(Request $request)
    {
        $param = $request->param();
        try {
            $this->reWriteLang($param);
            $this->setRedis();
            return Respons::run(1, '', '', '', '', new LangItemDetailModel(), lang('更新语言包'));
        } catch (\Exception $e) {
            return Respons::run(0, [], $e->getMessage());
        }

//        if(!empty($param)){
//            $this->reWriteLang($param['id']);
//        } else {
//            $this->reWriteLang();
//        }

    }

    private function reWriteLang($param = '')
    {

        $lang_config = [];
        $lang_arr = [];
        $this->LangModel
            ->where(['status' => 1])
            ->when(isset($param['id']), function ($query) use ($param) {
                $query->where(['id' => $param['id']]);
            })
            ->filter(function ($info) use (&$lang_arr) {
                $lang_arr[$info->id] = strtolower($info->lang);
            })
            ->order('is_default DESC, id ASC')
            ->field('lang,id')
            ->select();
        foreach ($lang_arr as $k => $v) {
            $this->LangItemsModel
                ->with(['getText' => function ($query) use ($k) {
                    $query->where(['lang_id' => $k]);
                }])
                ->distinct(true)
                ->chunk(1000, function ($data) use (&$lang_config, $v) {
                    foreach ($data as $val) {
                        $lang_config[$v][$val->title] = Util::replaceWebName($val->text);
                    }
                }, 'id', 'desc');
        }
//        $where[] = ['status', '=', 1];
//        if (isset($param['id'])) {
//            $where[] = ['id', '=', $param['id']];
//        }
//        $langs = LangModel::where($where)->order('is_default DESC, id ASC')->select();
//        $items = LangItemsModel::order('id DESC')->distinct(true)->select();
//        $lang_config = [];
//        foreach ($langs as $lang) {
//            $lang_config[strtolower($lang->lang)] = [];
//            foreach ($items as $item) {
//                $lang_config[strtolower($lang->lang)][$item->title] = $item->detail()->where('lang_id', $lang->id)->value('text');
//            }
//        }
        foreach ($lang_config as $lang => $config) {
            $config = myArrayFilter($config);
            if (empty($config)) {
                continue;
            }
            $lang_file = base_path("lang") . "{$lang}.php";
            $code = "<?php \r\nreturn " . var_export($config, true) . ";";
            $handle = fopen($lang_file, 'w');
            fwrite($handle, $code);
            fclose($handle);
        }
    }

    //导出数据
    public function excel(Request $request)
    {
        $langs = LangModel::where('status', 1)->order('id asc')->select();
        $sql = "SELECT ";
        foreach ($langs as $v) {
            $sql .= "MAX(CASE WHEN a.lang = '{$v['lang']}' THEN c.text END) AS {$v['lang']}_text,";
        }
        $sql .= " c.item_id, b.title FROM dq_lang a
CROSS JOIN dq_lang_items b
JOIN dq_lang_item_detail c ON a.id = c.lang_id AND b.id = c.item_id
GROUP BY c.item_id, b.title ORDER BY c.item_id DESC";
        $messages = Db::query($sql);
        $letter = ["B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L"];

        // 实例化
        $spreadsheet = new Spreadsheet();

        // 获取活动单元格
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getCell('A1')->setValue(lang('类目'));
        foreach ($langs as $key => $val) {
            $sheet->getCell($letter[$key] . '1')->setValue($val['title']);
        }

        foreach ($messages as $mk => $vo) {
            $n = $mk + 2;
            $sheet->getCell('A' . $n)->setValue($vo['title']);
            foreach ($langs as $key => $val) {
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
        return Respons::run(1, [
            'url' => $s3_url ?: toMedia($file_name, '/')
        ], '', '', '', new LangModel(), lang('语言类目数据导出'));
    }

    public function import(Request $request)
    {
        if (!empty($_FILES)) {
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
        return $this->handleImport($file);
    }

    private function getDetailIds()
    {
        $details = LangItemDetailModel::select();
        $detailIds = [];
        foreach ($details as $detail) {
            $detailIds["{$detail['item_id']}_{$detail['lang_id']}"] = $detail;
        }

        return $detailIds;
    }

    private function handleImport($file)
    {
        //此过程可能需要花费很多时间,设置php限制时间为0
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        $objReader = IOFactory::createReader('Xlsx');
        $ids = [];
        try {
            $objPHPExcel = $objReader->load($file);
            $sheet = $objPHPExcel->getSheet(0);
//            $highestRow = $sheet->getHighestRow();#不能用这个方式，否则文档中为空的也会计算，导致会有很多
            //通过循环查询类目
            $highestColumn = $sheet->getHighestColumn();
            $title = $items = $category = [];
            $arr = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O');
            foreach ($arr as $k => $currentColumn) {
                $title[] = $objPHPExcel->getActiveSheet()->getCell($currentColumn . "1")->getValue();
                if ($currentColumn == $highestColumn) {
                    //找到最大列，跳出循环
                    break;
                }
            }
            $category = [];
            $i = 2;
            $value = false;
            do {
                foreach ($title as $k => $v) {
                    $value = (string)$objPHPExcel->getActiveSheet()->getCell($arr[$k] . $i)->getValue();
                    if (!$value && $arr[$k] == 'A') {//只判断类目
                        break;
                    }
                    if ($v === "类目") {
                        $category[] = $value;
                    } else {
                        $items[$k][] = $value;
                    }
                }
                $i++;
            } while ($value || $arr[$k] != 'A');
            //不能用这个方式，否则文档中为空的也会计算，导致会有很多
//            for ($i = 2; $i <= $highestRow; $i++) {
//                foreach($title as $k => $v){
//                    $value = (string)$objPHPExcel->getActiveSheet()->getCell($arr[$k] . $i)->getValue();
//                    if($v === "类目"){
//                        $category[] = $value;
//                    }else{
//                        $items[$k][] = $value;
//                    }
//                }
//            }

            /* 获取语言种类Id*/
            $lang = [];
            foreach ($title as $k => $v) {
                $langId = LangModel::where(['title' => $v])->value('id');
                if (empty($langId)) {
                    $lang[$k] = '';
                } else {
                    $lang[$k] = $langId;
                }
            }
            if (!empty($category)) {
                // 获取语言项目ID和锁定状态
                $itemIds = LangItemsModel::column('id', 'title');
                $is_locks = LangItemsModel::column('is_lock', 'id');
                // 获取详细信息ID
                $detailIds = $this->getDetailIds();

                // 准备插入语言项目的SQL语句
                $itemInsertSql = "INSERT INTO `dq_lang_items` (`title`) VALUES ";
                $itemInsertData = [];

                // 遍历类别，准备插入新的语言项目
                foreach ($category as $key => $val) {
                    if (empty($val)) {
                        continue;
                    }
                    $itemId = $itemIds[$val] ?? 0;
                    if (empty($itemId)) {
                        $itemInsertData[] = "('{$val}')";
                    }
                }

                // 如果有新的语言项目，执行插入操作
                if (!empty($itemInsertData)) {
                    $itemInsertSql .= implode(',', $itemInsertData) . ";";
                    Db::execute($itemInsertSql);
                    // 更新语言项目ID列表
                    $itemIds = LangItemsModel::column('id', 'title');
                }

                // 准备插入语言项目详细信息的SQL语句
                $textInsertSql = "INSERT INTO `dq_lang_item_detail` (`item_id`,`lang_id`,`text`) VALUES ";
                $textInsertData = [];

                // 遍历类别和语言，准备插入新的详细信息
                foreach ($category as $key => $val) {
                    $itemId = $itemIds[$val] ?? 0;
                    if (empty($itemId)) {
                        continue;
                    }
                    foreach ($items as $k => $v) {
                        $langId = $lang[$k];
                        if ($langId) {
                            $text = $v[$key];
                            if (empty($text)) {
                                $text = '';
                            }
                            $detailId = $detailIds["{$itemId}_{$langId}"] ?? [];
                            if (empty($detailId)) {
                                $data = [$itemId, $langId, addslashes($text)];
                                $textInsertData[] = "(\"" . implode('","', $data) . "\")";
                            }
                        }
                    }
                }

                // 如果有新的详细信息，执行插入操作
                if (!empty($textInsertData)) {
                    $textInsertSql .= implode(",\n", $textInsertData) . ";";
                    Db::execute($textInsertSql);
                    // 更新详细信息ID列表
                    $detailIds = $this->getDetailIds();
                }
                // 遍历类别和语言，更新已存在的详细信息
                foreach ($category as $key => $val) {
                    $itemId = $itemIds[$val] ?? 0;
                    if (empty($itemId)) {
                        continue;
                    } else {
                        $is_lock = $is_locks[$itemId] ?? 0;
                    }
                    if ($is_lock == 0) {
                        foreach ($items as $k => $v) {
                            $langId = $lang[$k];
                            if ($langId) {
                                $text = $v[$key];
                                if (empty($text)) {
                                    $text = '';
                                }
                                $detail = $detailIds["{$itemId}_{$langId}"] ?? [];
                                if (isset($detail['id']) && $text != $detail['text']) {
                                    // 更新已存在的详细信息
                                    LangItemDetailModel::where('id', $detail['id'])->update(['text' => $text]);
                                    $ids[] = $detail['id'];
                                }
                            }
                        }
                    }
                }
            }
            $ids['id'] = empty($ids) ? '' : implode(',', $ids);
            Respons::run(1, '', '', '', $ids, new LangItemDetailModel(), lang('语言类目导入'));
        } catch (\Exception $t) {
            return json(["code" => 0, 'msg' => $t->getMessage(), 'error' => $t->getTrace()]);
        }
        return json(["code" => 1, 'msg' => '上传成功']);
    }

    /**
     * 增删改查 加缓存
     * @return void
     */
    public function setRedis()
    {
        /* @var CommomRedis */
        app()->make(CommomRedis::class)->set("system_lang", Util::getLangs(), 86400);
        app()->make(CommomRedis::class)->set("system_web_lang", Util::getLangsWeb(), 86400);
        app()->make(ApiRedis::class)->delete("default_country");

    }
}
