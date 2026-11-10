<?php
declare (strict_types = 1);

namespace app\command;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\redis\ApiRedis;
use think\facade\Db;

class Transfer extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('transfer')
            ->addArgument("userid", Argument::REQUIRED, '')
            ->setDescription('Transfer items');
    }

    protected function execute(Input $input, Output $output)
    {
        $userid = $input->getArgument('userid');
        $user = UserModel::where("tree like '/".$userid."/%'")->order('id asc')->column("id");        
        foreach($user as $v){
            $title = "Recordatorio";
            $msg = "Recordatorio: Debido a la grave situación epidémica, los principales tutores de agentes están actualmente aislados debido a la epidemia. Ingrese al grupo de telegramas para encontrar al administrador principal tutor e ingrese al grupo de organización de eventos del plan diario de preservación de capital. El jefe instructor le emitirá el plan diario de preservación de capital para la competencia Bet.
El instructor jefe se hará cargo del próximo trabajo, por favor no se asuste, nuestro trabajo seguirá funcionando normalmente. Disculpen las molestias ocasionadas por la epidemia! Para que todos experimenten mejor la alegría de invertir y ganar dinero, el equipo lanzará mejores actividades de bienestar para compensar a todos. Para no afectar el uso normal de todos, ingrese rápidamente al canal de Telegram para comunicarse con el tutor y emitir un plan de preservación de capital para usted.";
            UserMsgModel::add_msg($v, $title, $msg, 3, 0);
            $output->writeln("{$v}===".date("Y-m-d H:i:s"));
        }
    }
}
