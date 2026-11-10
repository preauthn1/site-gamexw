<?php

namespace app\command;

use app\common\model\UserModel;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;
use think\facade\Queue;

class UpdateAllDecimal extends Command{

    //TODO 该程序只能在0点1分后执行
    protected function configure()
    {
        // 指令配置
        $this->setName('updatedecimal:all')
            ->setDescription('update all table decimal decimal(20,4)');//升级昨天到现在用户VIP
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        $output->writeln("开始执行decimal修改");
        $this->handleAllTableUpdateDecimal();
        $output->writeln("decimal修改执行完毕");
        $this->log();
    }

    //处理VIP升级
    private function handleAllTableUpdateDecimal(){
        $sql = "show tables";
        $tables = Db::query($sql);
        foreach ($tables as $table){
            $tableName = reset($table);
            $this->output->writeln("开始处理表：{$tableName}");
            $sql = "SHOW COLUMNS FROM {$tableName};";
            $fields = Db::query($sql);
            $updateFieldArr = [];
            foreach ($fields as $v){
                if(strpos($v['Type'], 'decimal') !== false && $v['Type'] != 'decimal(20,4)') {
                    //如果没有默认值则需要先修改默认值，否之执行修改decimal加上默认值会报错
                    //需要两步，第一步将空''设置为null，第二步将null设置成0，直接''设置成0有报错，因为''不是有效的decimal值
                    if( $v['Default'] === ''  ){
                        $_sql = "UPDATE `{$tableName}` SET `{$v['Field']}` = null WHERE `{$v['Field']}` = '';";
                        $this->output->error("表[{$tableName}]字段[{$v['Field']}]值为'', 设置为null, 执行SQL：{$_sql}");
                        Db::execute($_sql);
                    }
                    if( $v['Default'] === null  ){
                        $_sql = "UPDATE `{$tableName}` SET `{$v['Field']}` = 0 WHERE `{$v['Field']}` IS NULL";
                        $this->output->error("表[{$tableName}]字段[{$v['Field']}]值为null, 设置为0, 执行SQL：{$_sql}");
                        Db::execute($_sql);
                    }
                    //匹配长度和精度，如果数据库高于设置的则以数据库为准
                    $precision = 20;
                    $scale = 4;
                    $parsed = $this->parseDecimalType(strtoupper($v['Type']));
                    if( $parsed ){
                        $precision = max($parsed['precision'], 20);
                        $scale = max($parsed['scale'], 4);
                    }
                    if( "decimal({$precision},{$scale})" !=  $v['Type']){
                        $updateFieldArr[] = "MODIFY `{$v['Field']}` decimal({$precision},{$scale}) NOT NULL DEFAULT 0";
                    }
                }
            }
            //处理修改decimal(20,4)
            if( $updateFieldArr ){
                $sql = "ALTER TABLE `{$tableName}` ".implode(", ", $updateFieldArr).";";
                $this->output->error("表[{$tableName}]decimal(20,4)处理中, 执行SQL：{$sql}");
                Db::execute($sql);
            }
            $this->output->info("表[{$tableName}]处理完毕");
        }
    }

    private function parseDecimalType($decimalType) {
        // 使用正则表达式提取精度和小数位数
        if (preg_match('/DECIMAL\((\d+),(\d+)\)/', strtoupper($decimalType), $matches)) {
            // $matches[1] 是总位数（precision），$matches[2] 是小数位数（scale）
            return [
                'precision' => (int)$matches[1],
                'scale' => (int)$matches[2]
            ];
        }
        return null; // 如果没有匹配到，则返回 null
    }

    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}