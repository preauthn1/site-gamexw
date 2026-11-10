<?php

namespace app\command;

use app\common\lib\Uploads;
use app\common\lib\Util;
use GuzzleHttp\Client;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Exception;
use think\facade\Db;
use think\facade\Env;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Intervention\Image\ImageManager;
class SaveS3 extends Command {

    protected function configure() {
        $this->setName('saves3')->setDescription('上传图片到S3')
            ->addArgument("type", Argument::OPTIONAL, '方法，默认s3', 's3')
            ->addArgument("arg", Argument::OPTIONAL, '参数')
            ->setDescription('public task');
    }
    protected function execute(Input $input, Output $output) {
        $type = $input->getArgument('type');
        if (method_exists($this, $type)) {
            $this->{$type}($input, $output);
        } else {
            $output->info("type 错误: {$type}");
        }
    }

    protected function s3(Input $input, Output $output) {
        $list = Db::name('games')->where('g_icon', "<>", "")->whereNotLike('g_icon', "https://%")->select();
        if (!empty($list)) {
            $uploader = new Uploads();
            $curr = 1;
            $total = count($list);
            foreach ($list as $item) {
                echo "{$curr}/{$total};";
                $logo = toMedia($item['g_icon'], 2);
                if (!file_exists($logo)) {
                    $curr++;
                    $output->info($item['g_icon']);
                    continue;
                }
                $p = pathinfo($item['g_icon']);
                $name = strtolower(Util::random(16));
                $key = "storage/" . $p['dirname'] . "/{$name}.{$p['extension']}";
                $url = $uploader->uploadS3($logo, $key);
                if ($url) {
                    Db::name('games')->where('g_id', $item['g_id'])->update([
                        'g_icon' => $url,
                    ]);
                    Db::name('game_infos')->where('gi_gid', $item['g_id'])->update([
                        'gi_headimg' => $url,
                    ]);
                    $output->info($url);
                }else{
                    $output->error($item['g_icon']);
                }
                $curr++;
            }
        }
        $output->info("done！");
    }
    
    public function save($key) {
        $s3_config = [
            'version' => 'latest',
            'region' => Env::get('S3.REGION'),
            'suppress_php_deprecation_warning' => true,
            'credentials' => [
                'key' => Env::get('S3.CREDENTIALS_KEY'),
                'secret' => Env::get('S3.CREDENTIALS_SECRET'),
            ],
        ];
        $s3_bucket = Env::get('S3.BUCKET');
        $file_path = toMedia($key, '/', 2);
        if (file_exists($file_path)) {
            return true;
        }
        $pathinfo = pathinfo($file_path);
        if (!file_exists($pathinfo['dirname'])) {
            $a = mkdir($pathinfo['dirname']);
        }
        if ($s3_bucket && isset($s3_config['credentials']['key']) && $s3_config['credentials']['key'] != '') {
            $s3 = S3Client::factory($s3_config);
            try {
                $result = $s3->getObject([
                    'Bucket' => $s3_bucket,
                    'Key' => ltrim($key, '/'),
                ]);

                file_put_contents($file_path, $result['Body']);
            } catch (S3Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }
        
        return true;
    }

    protected function image(Input $input, Output $output) {
        $arg = $input->getArgument('arg');
        $args = explode('-', $arg);
        if (count($args) != 4) {
            throw new Exception("参数格式:图片目录-输出目录-新图片宽-新图片高");
        }
        $src_dir = public_path($args[0]);
        $dst_dir = public_path($args[1]);
        $new_width = $args[2]; // 564
        $new_height = $args[3];// 737
        $output->info("{$src_dir}-{$dst_dir}-{$new_width}-{$new_height}");
//        return;
        $handler = opendir($src_dir);
        while (($filename = readdir($handler)) !== false) {//务必使用!==，防止目录下出现类似文件名“0”等情况
            if ($filename != "." && $filename != "..") {
                $p = pathinfo($filename);
                if (isset($p['extension']) && in_array($p['extension'], ['jpg', 'png', 'webp'])) {
                    $src_file = rtrim($src_dir, '/') . "/{$filename}";
                    $dst_file = rtrim($dst_dir, '/') . "/{$filename}";
                    $this->resetImg($src_file, $dst_file, $new_width, $new_height);
                    $output->info($dst_file);
                }
            }
        }
        closedir($handler);

        $output->info('done!');
    }

    protected function resetImg($src_file, $dst_file, $new_width, $new_height) {
        $w_rate = $new_width / $new_height;
        $manager = new ImageManager(['driver' => 'gd']);
        $img = $manager->make($src_file);
        $old_width = $img->getWidth();
        $old_height = $img->getHeight();
        if ($old_width >= $old_height) {
            $new_width2 = $old_width;
            $new_height2 = round($old_width / $w_rate);
        } else {
            $new_height2 = $old_height;
            $new_width2 = round($old_height * $w_rate);
        }
//        echo "{$old_width}--{$old_height}--{$new_width}xxx{$new_height}###{$new_width2}--{$new_height2}\n";
        $img->resizeCanvas($new_width2, $new_height2);
        $img->save($dst_file);
    }

    /**
     * 同步所有图片到当前S3 图片
     * @return void
     */
    protected function syncLocalS3() {
        //表字段中内容中含有图片的表
        $contentTable = self::getContentTable();
        foreach ($contentTable AS $t) {
            $this->syncContentTable($t['name'], $t['pk'], $t['field']);
        }


        //表字段中只有图片
        $tables = self::getTables();
        foreach ($tables AS $t) {
            $this->syncTable($t['name'], $t['pk'], $t['field']);
        }
    }

    //表字段值为图片
    protected function syncTable($table_name, $pk, $field) {
        $s3_domain = Env::get('S3.DOMAIN');
        $list = Db::name($table_name)->where($field, "<>", "")->whereNotLike($field, "%{$s3_domain}%")->select();
        foreach ($list as $row) {
            $new_url = $this->download($row[$field]);
            if ($new_url && $new_url != $row[$field]) {
                $ret = Db::name($table_name)->where($pk, $row[$pk])->update([$field => $new_url]);
                $this->output->writeln("更新:{$row[$pk]}->{$ret}");
            }
        }
    }

    //表字段值为内容，里面包含图片
    protected function syncContentTable($table_name, $pk, $field) {
        $s3_domain = Env::get('S3.DOMAIN');
        $list = Db::name($table_name)->where($field, "<>", "")->where($field, 'like', "http%")->whereNotLike($field, "{$s3_domain}%")->select();
        foreach ($list as $row) {
            // 正则表达式匹配图片链接（包括富文本和普通文本中的链接）
            $pattern = '/https?:\/\/[^\s"]+?\.(?:jpg|jpeg|png|gif|svg)/i';
            $content = $row[$field];
            // 执行匹配
            preg_match_all($pattern, $content, $matches);
            // 获取匹配到的所有图片链接
            $imageLinks = $matches[0];
            foreach ($imageLinks as $imageLink) {
                $new_url = $this->download($imageLink);
                if ($new_url && $new_url != $imageLink) {
                    $content = str_replace($imageLink, $new_url, $content);
                }
            }
            if( $content != $row[$field] ){
                $ret = Db::name($table_name)->where($pk, $row[$pk])->update([$field => $content]);
                $this->output->writeln("更新:{$row[$pk]}->{$ret}");
            }else{
                $this->output->writeln("无需更新");
            }
        }
    }

    protected function download($url) {
        $client = new Client();
        $s3url = $url;
        try {
            $pi = parse_url($url);
            $sink = public_path() . ltrim($pi['path'], '/');
            $path = pathinfo($sink);
            if (!is_dir($path['dirname'])) {
                mkdir($path['dirname'], 0777, true);
            }
            $client->request('GET', trim($url), ['sink' => $sink]);
            $uploader = new Uploads();
            $s3url = $uploader->uploadS3($sink, ltrim($pi['path'], '/'));
            $this->output->writeln($sink . "===ok-");
            $this->output->writeln($url . "--old===ok-");
            $this->output->writeln($s3url . "--new===ok-" . Util::timeToStr());
        } catch (\Exception $exc) {
            $this->output->writeln($url . "===fail-" . Util::timeToStr() . $exc->getMessage());
        }
        $this->output->writeln("done!");
        return $s3url;
    }

    //内容里面包含链接
    private static function getContentTable(){
        return [
            ['name' => 'action', 'pk' => 'id', 'field' => 'c_label'],
            ['name' => 'jackpot_content', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'action', 'pk' => 'id', 'field' => 'c_detail'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'share_intro'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'service_info'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'issues_list'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'rule_info'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'pop_info'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'pop_explain'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'calculate_text'],
            ['name' => 'agentmessage', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'agentmessagefast', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'gonggao', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'journalism', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'lottery_config', 'pk' => 'id', 'field' => 'rule'],
            ['name' => 'lottery_news', 'pk' => 'id', 'field' => 'news_content'],
            ['name' => 'news', 'pk' => 'id', 'field' => 'news_content'],
            ['name' => 'pdd_turntable', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'pdd_news', 'pk' => 'id', 'field' => 'news_content'],
            ['name' => 'receive_code', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'red_packet', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'rocket_activity', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'rule', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'rule_index', 'pk' => 'id', 'field' => 'content'],
            ['name' => 'sign', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'sign', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'sign_news', 'pk' => 'id', 'field' => 'news_content'],
            ['name' => 'wufu', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'wufu_content', 'pk' => 'id', 'field' => 'introduce'],
            ['name' => 'lang_item_detail', 'pk' => 'id', 'field' => 'text'],
            ['name' => 'system_lang', 'pk' => 'id', 'field' => 'text'],
        ];
    }

    //单独是链接
    private static function getTables(){
        return [
            ['name' => 'banner', 'pk' => 'id', 'field' => 'img'],
            ['name' => 'action', 'pk' => 'id', 'field' => 'c_img'],
            ['name' => 'news', 'pk' => 'id', 'field' => 'news_images'],
            ['name' => 'news', 'pk' => 'id', 'field' => 'first_recharge_images'],
            ['name' => 'news', 'pk' => 'id', 'field' => 'recharge_images'],
            ['name' => 'journalism', 'pk' => 'id', 'field' => 'pic'], // content需要特殊处理
            ['name' => 'lang', 'pk' => 'id', 'field' => 'flag'],
            ['name' => 'nation', 'pk' => 'id', 'field' => 'flag'],
            ['name' => 'fake_game_set', 'pk' => 'id', 'field' => 'game_icon'],
            ['name' => 'game_set', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'game_type', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'lottery_prize', 'pk' => 'id', 'field' => 'pic'],
            ['name' => 'lottery_config', 'pk' => 'id', 'field' => 'img'],
            ['name' => 's_lottery', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'service', 'pk' => 'id', 'field' => 'img'],
            ['name' => 'third_configs', 'pk' => 'tc_id', 'field' => 'icon'],
            ['name' => 'third_configs', 'pk' => 'tc_id', 'field' => 'simple_icon'],
            ['name' => 'third_set', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'user_offline', 'pk' => 'id', 'field' => 'credentials'],
            ['name' => 'pay_set', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'liansai_team', 'pk' => 'id', 'field' => 'logo'],
            ['name' => 'games', 'pk' => 'g_id', 'field' => 'g_icon'],#注释这三项上面有
            ['name' => 'game_infos', 'pk' => 'gi_id', 'field' => 'gi_headimg'],
            ['name' => 'user_level', 'pk' => 'id', 'field' => 'image'],
            ['name' => 'sys_sets', 'pk' => 'set_id', 'field' => 'set_value'],
            ['name' => 'action_type', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'advertising', 'pk' => 'id', 'field' => 'images'],
            ['name' => 'affiliate', 'pk' => 'id', 'field' => 'team_url'],
            ['name' => 'affiliate_platform', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'bank', 'pk' => 'id', 'field' => 'bank_logo'],
            ['name' => 'bank_sk', 'pk' => 'id', 'field' => 'bank_qrcode'],
            ['name' => 'currency', 'pk' => 'id', 'field' => 'symbol_img'],
            ['name' => 'default_head', 'pk' => 'id', 'field' => 'url'],
            ['name' => 'forecast', 'pk' => 'id', 'field' => 'banner_url'],
            ['name' => 'gonggao_type', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'index_menu', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'index_menu', 'pk' => 'id', 'field' => 'icon_url'],
            ['name' => 'lottery_news', 'pk' => 'id', 'field' => 'news_images'],
            ['name' => 'music', 'pk' => 'id', 'field' => 'file'],
            ['name' => 'pay_type', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'payment_type', 'pk' => 'id', 'field' => 'icon'],
            ['name' => 'pdd_news', 'pk' => 'id', 'field' => 'news_images'],
            ['name' => 'receive_code', 'pk' => 'id', 'field' => 'image'],
            ['name' => 'task', 'pk' => 'id', 'field' => 'background_img'],
            ['name' => 'user', 'pk' => 'id', 'field' => 'head_img'],
            ['name' => 'wufu', 'pk' => 'id', 'field' => 'image'],
            ['name' => 'wufu_cards', 'pk' => 'id', 'field' => 'image'],
        ];
    }

}
