<?php
declare (strict_types = 1);
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use app\api\redis\ApiRedis;
use think\facade\Queue;
use think\facade\Log;

class Task extends Command {
    private $jobs = ['listen', 'agent','upgrade'];
    protected function configure() {
        $this->setName('task')->setDescription('创建队列任务,请勿重复执行');
        $this->addArgument('job', Argument::OPTIONAL, "可选参数：" . implode(",", $this->jobs))
            ->addOption('delete', 'd', Option::VALUE_NONE, '删除队列');
    }

    protected function execute(Input $input, Output $output) {
        $job = $input->getArgument("job");
        $ApiRedis=new ApiRedis();
        if(!is_null($job)) {
            if (!in_array($job, $this->jobs)) {
                throw new \InvalidArgumentException(sprintf('参数 "%s" 不在可选参数范围内：' .  implode(",", $this->jobs), (string) $job));
            }
        }else{
            throw new \InvalidArgumentException('请填写参数，可选参数：' .  implode(",", $this->jobs));
        }
        
        if ($job == "agent") {
            if ($input->hasOption("delete")) {
                $ApiRedis->set("deleteQueue:Agent", true, 7*86400);
                $output->writeln("创建删除代理返佣监听任务成功");
            }else{
                $ApiRedis->delete("deleteQueue:Agent");
                $res = Queue::push("app\job\Listen@agent", ['page' => 0], 'task');
                $output->writeln("创建代理返佣监听任务成功");
            }
        }

        if ($job == "upgrade") {
            if ($input->hasOption("delete")) {
                $ApiRedis->set("deleteQueue:Upgrade", true, 7*86400);
                $output->writeln("创建删除vip升级任务成功");
            }else{
                $ApiRedis->delete("deleteQueue:Upgrade");
                $res = Queue::push("app\job\Listen@upgrade", "", 'upgrade');
                $output->writeln("创建vip升级任务成功");
            }
        }
    }
}
