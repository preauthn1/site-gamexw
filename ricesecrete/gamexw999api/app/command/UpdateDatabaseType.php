<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;

class UpdateDatabaseType extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('update:databaseType')
            ->setDescription('update database type');//修改数据库类型为utf8mb4
    }

    protected function execute(Input $input, Output $output)
    {
        $noUpdateTableArr = [];
        $tablesMessage = Db::query('SELECT table_name, table_collation FROM information_schema.tables WHERE table_schema = DATABASE()');
        foreach ($tablesMessage as $v){
            if($v['table_collation'] == 'utf8mb4_general_ci'){
                $noUpdateTableArr[] = $v['table_name'];
            }
        }
        $tables = Db::query('SHOW TABLES');
        $tables = array_column($tables, 'Tables_in_' . env('database.database'));

        foreach ($tables as $tableName){
            if( in_array($tableName, $noUpdateTableArr) ){
                continue;
            }
            echo $tableName;
            $sql = "ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 ";
            Db::query($sql);
            $sql1 = "ALTER TABLE `{$tableName}` COLLATE = utf8mb4_general_ci";
            Db::query($sql1);
            $this->log("update $tableName success");
        }
        $this->log("all table update success");
    }


    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}