<?php

namespace app\command;

use app\common\model\AdminNodeModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class NodeAuths  extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('node:auth')
            ->setDescription('新管理后台批量处理权限');
    }

    protected function execute(Input $input, Output $output) {
        $this->createWelcomeNode();
        $auth = $this->getAuth();
        foreach ($auth as $item) {
            $p_node = AdminNodeModel::where('node_name', $item['name'])->where('pid', '>', 0)->find();
            if (empty($p_node)) {
                continue;
            }
            $sort = 1;
            foreach ($item['auth'] AS $row) {
                $node = AdminNodeModel::where('pid', $p_node['id'])->where('path', $row['path'])->value('id');
                if (empty($node)) {
                    AdminNodeModel::create([
                        'node_name' => $row['name'],
                        'node_url' => $row['path'],
                        'path' => $row['path'],
                        'name' => $row['path'],
                        'pid' => $p_node['id'],
                        'special_menu' => $p_node['special_menu'],
                        'is_admin' => $p_node['is_admin'],
                        'not' => $p_node['not'],
                        'sort' => $sort
                    ]);
                    $output->writeln("{$item['name']}--{$row['name']}-{$row['path']}");
                }
                $sort++;
            }
        }
        $output->writeln('done!');
    }

    private function createWelcomeNode() {
        $pid = AdminNodeModel::where('node_name', '仪表盘')->where('pid', 0)->value('id');
        if (!empty($pid)) {
            return;
        }

        $node = AdminNodeModel::create([
            'node_name' => '仪表盘',
            'node_url' => '/welcome',
            'path' => '/welcome',
            'name' => 'welcome2',
            'pid' => 0,
            'sort' => 1
        ]);

        AdminNodeModel::create([
            'node_name' => '仪表盘',
            'node_url' => '/welcome',
            'path' => '/welcome',
            'name' => 'Welcome',
            'pid' => $node->id,
            'sort' => 1
        ]);
    }

    private function getAuth() {
        return [
            [
                "name" => "仪表盘",
                "auth" => [
                    [
                        "path" => "dataView",
                        "name" => "数据总览"
                    ]
                ]
            ],
            [
                "name" => "会员列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ],
                    [
                        "path" => "profile",
                        "name" => "资料"
                    ],
                    [
                        "path" => "edit_super",
                        "name" => "修改上级"
                    ],
                    [
                        "path" => "water",
                        "name" => "批量清0流水"
                    ],
                    [
                        "path" => "ceshi",
                        "name" => "测试用户列表"
                    ],
                    [
                        "path" => "hide",
                        "name" => "隐藏部分用户信息"
                    ]
                ]
            ],
            [
                "name" => "代理列表",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "审核"
                    ],
                    [
                        "path" => "profile",
                        "name" => "资料"
                    ]
                ]
            ],
            [
                "name" => "会员等级",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ]
                ]
            ],
            [
                "name" => "会员提现账号",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "recycle_bin",
                        "name" => "回收站"
                    ]
                ]
            ],
            [
                "name" => "会员登录日志",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "会员报表",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "用户标签",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "标签列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "用户充值",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "手动入单"
                    ],
                    [
                        "path" => "edit",
                        "name" => "审核"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "用户提现",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "手动入单"
                    ],
                    [
                        "path" => "edit",
                        "name" => "审核"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "人工调整",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "手动入单"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "游戏分类",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "back",
                        "name" => "返水设置"
                    ]
                ]
            ],
            [
                "name" => "游戏平台",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ]
                ]
            ],
            [
                "name" => "游戏管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "batch",
                        "name" => "批量修改状态"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ]
                ]
            ],
            [
                "name" => "游戏记录",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "游戏报表",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "用户日报表",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "留存率报表",
                "auth" => [
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "返佣设置",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ]
                ]
            ],
            [
                "name" => "代理返佣",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "派发审核"
                    ]
                ]
            ],
            [
                "name" => "外部代理返佣",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "派发审核"
                    ]
                ]
            ],
            [
                "name" => "返水管理",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "派发审核"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "优惠管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "active",
                        "name" => "活动内容设置"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                ]
            ],
            [
                "name" => "申请管理",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "审核"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "奖品列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "抽奖记录",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加假数据"
                    ]
                ]
            ],
            [
                "name" => "积分记录",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "手动调整"
                    ]
                ]
            ],
            [
                "name" => "类型管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "链接管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "基金列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "live",
                        "name" => "活期设置"
                    ]
                ]
            ],
            [
                "name" => "基金订单",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "手动退回"
                    ]
                ]
            ],
            [
                "name" => "线下收款账户",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "set",
                        "name" => "备注设定"
                    ]
                ]
            ],
            [
                "name" => "支付平台",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ]
                ]
            ],
            [
                "name" => "货币管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "set",
                        "name" => "实时汇率设置"
                    ]
                ]
            ],
            [
                "name" => "银商管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "record",
                        "name" => "明细"
                    ],
                    [
                        "path" => "recharge",
                        "name" => "上分"
                    ]
                ]
            ],
            [
                "name" => "首页轮播图",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "公告管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "基础问答",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "客服管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "规则管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "站内信",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "新闻管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "新闻类别",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "三方短信管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "管理员列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "角色管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "三方登录管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ]
                ]
            ],
            [
                "name" => "IP黑名单",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "三方登陆管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "代理返佣",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "派发"
                    ]
                ]
            ],
            [
                "name" => "推广奖金",
                "auth" => [
                    [
                        "path" => "edit",
                        "name" => "派发"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "微商设置",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "游戏配置",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "榜单设定",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "假账户管理",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "国家列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "语言列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "语言种类",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ]
                ]
            ],
            [
                "name" => "语言类目",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "lock",
                        "name" => "锁定/解锁"
                    ],
                    [
                        "path" => "import",
                        "name" => "导入"
                    ],
                    [
                        "path" => "export",
                        "name" => "导出"
                    ]
                ]
            ],
            [
                "name" => "彩种列表",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "添加"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ],
                    [
                        "path" => "delete",
                        "name" => "删除"
                    ],
                    [
                        "path" => "odds",
                        "name" => "赔率设置"
                    ]
                ]
            ],
            [
                "name" => "开奖设置",
                "auth" => [
                    [
                        "path" => "add",
                        "name" => "预设开奖"
                    ],
                    [
                        "path" => "edit",
                        "name" => "修改"
                    ]
                ]
            ],
            [
                "name" => "广告渠道管理",
                "auth" => [
                    [
                        "path" => "report",
                        "name" => "报表"
                    ]
                ]
            ]
        ];
    }
}