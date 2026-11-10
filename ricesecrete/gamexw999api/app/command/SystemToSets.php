<?php

namespace app\command;

use app\model\SysSets;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class SystemToSets extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('system_to_sets')
            ->setDescription('dq_system迁移到dq_sys_sets');
    }

    protected function execute(Input $input, Output $output)
    {
        $fields = Db::getFields("dq_system");
        $info = Db::table("dq_system")->where('id', 1)->find();
//        dump($info);
        foreach ($fields as $field) {
            if (in_array($field['name'], ['id', 'about_us', 'general', 'fbd_rule', 'agent_rule', 'recharge_rule', 'reg_service_rule', 'tournament_notes'])) {
                continue;
            }

            $s = Db::name('sys_sets')->where('set_code', $field['name'])->find();
            if ($s) {
                continue;
            }
            $type = $this->getType(strtolower($field['type']));
            $row = [
                'set_label' => '基础设置',
                'set_title' => $field['comment'],
                'set_code' => $field['name'],
                'set_type' => $this->getType(strtolower($field['type'])),
                'set_info' => $field['comment'],
//                'set_value' => $info[$field['name']],
                'set_state' => 1,
            ];
            if ($type == 'text') {
                $row['set_valuetext'] = $info[$field['name']];
            } else {
                $row['set_value'] = $info[$field['name']];
            }
            Db::name('sys_sets')->insert($row);
        }
        SysSets::buildCache();
        $output->writeln('done!');
    }

    private function getType($type) {
        // 'int','uint','number','unumber','string','text','list','bool','upload'
        if (strexists($type, 'tinyint')) {
            return "int";
        } elseif (strexists($type, 'int')) {
            return "int";
        } elseif (strexists($type, 'decimal') || strexists($type, 'float')) {
            return "number";
        } elseif (strexists($type, 'text')) {
            return "text";
        }
        return "string";
    }

}