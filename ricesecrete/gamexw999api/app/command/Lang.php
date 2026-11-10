<?php
declare (strict_types = 1);

namespace app\command;
use app\api\factory\SystemFactory;
use app\common\model\LangModel;
use app\common\model\LangItemsModel;
use app\common\model\LangItemDetailModel;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\redis\ApiRedis;
use think\facade\Db;

class Lang extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('lang')
            ->setDescription('lang items');
    }

    protected function execute(Input $input, Output $output)
    {
        $cache = new ApiRedis();
        $key = md5('updateLangTime:' . gethostname());
        if(!$cache->has($key)){
            $cache->set($key, 1);
            echo "开始更新";
            $where[] = ['status', '=', 1];
            $langs = LangModel::where($where)->order('is_default DESC, id ASC')->select();
            $items = LangItemsModel::order('id DESC')->distinct(true)->select();
            foreach($langs as $key => $val){
                $this->update($val, $items);
            }
            //生成静态文件
            $rlt = SystemFactory::info_static();
            Utils::writeToStaticFile('system.info.json', json_encode($rlt));
        }
    }
    
    protected function update($lang, $items){
        $lang_config[strtolower($lang->lang)] = [];
        foreach ($items as $item) {
            $lang_config[strtolower($lang->lang)][$item->title] = $item->detail()->where('lang_id', $lang->id)->value('text');
        }
        
        foreach ($lang_config as $lang => $config) {
            echo $lang;
            $lang_file = base_path("lang") . "{$lang}.php";
            $code = "<?php \r\nreturn " . var_export(myArrayFilter($config), true) . ";";
            $handle = fopen($lang_file, 'w');
            fwrite($handle, $code);
            fclose($handle);
        }
    }
}
