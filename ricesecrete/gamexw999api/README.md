# dq

#### .env配置
;三方平台相关配置
;充值手续费（除过百分比的，比如 0.01表示1%
DEPOSIT_FEE=0.005
;提现手续费（除过百分比的，比如0.01表示1%
WITHDRAW_FEE=0.005
;负盈分佣模式下技术服务费阶梯 格式为（金额从大到小，每一段表示超过多少金额应用对应的比率）：起始金额|服务费比率,...
FYFY_TEC_RATE=100000|0.01,10000|0.005,1000|0.002,0|0.001
;负盈分佣模式下当月负盈忽略金额（负数），负盈大于此值的忽略
FYFY_LOSE_LINEAMOUNT= -10000

;三方平台用户投注报表每次任务统计注单条数
STATS_USER_TIME_STEP= 20000
;三方平台综合统计报表分表月份间隔
STATS_GLOBAL_TABLE_SPLIT_MONTH = 3

#### 计划任务

1. 采集赛事(5分钟)
cd /www/wwwroot/目录 && php think match

2. 更新赛事结算状态(3分钟)
cd /www/wwwroot/目录 && php think updatematch

3. 更新当天运营总表(5分钟)
cd /www/wwwroot/目录 && php think updatereport 1

4. 更新前2天运营总表 (每天任务)
cd /www/wwwroot/目录 && php think updatereport 2

5. 添加赛程(分钟任务)
cd /www/wwwroot/目录 && php think addleague

9.静态文件生成(每分钟)
cd /www/wwwroot/目录 && php think taskmin

10.取消充值订单(每天凌晨7分)
cd /www/wwwroot/目录 && php think cancel 3

10.同步多语言(每分钟)
cd /www/wwwroot/目录 && php think lang

11.三方游戏游戏记录(10分钟)
cd /www/wwwroot/目录 && php think game

12.三方平台游戏报表(凌晨 15分)
cd /www/wwwroot/目录  && php think game_report

13.三方游戏游戏记录 按天获取(凌晨30分)
cd /www/wwwroot/目录 && php think game day

14.更新队伍LOGO(每两小时)
cd /www/wwwroot/目录 && php think team 2

15.获取平台注单(5分钟)
cd /www/wwwroot/目录 && php think thirdtask

16.返水和返佣统计(每天凌晨1点)
cd /www/wwwroot/目录 && php think jstask

17.返佣自动发放(每月2号2点)（受配置控制）
cd /www/wwwroot/目录 && php think jspaytask

18.用户报表（5分钟）
cd /www/wwwroot/目录 && php think UserReport

19.自动派发推广奖励
cd /www/wwwroot/目录 && php think AgentSell


20.手动获取主动模式下的注单!!!!!（参数自己调配）
cd /www/wwwroot/目录 && php think manualtask


21.每天 2.30 获取og游戏列表
cd /www/wwwroot/目录 && php think gamelisttask

22.每分钟 彩票开奖
cd /www/wwwroot/目录 && php think lottery:draw

23.每天 统计会员报表
cd /www/wwwroot/目录 && php think public 3

24.每天23点 统计三方对账报表
cd /www/wwwroot/目录 && php think third:report

25.Telegram机器人自动发消息（游戏派彩 - 时间频率根据自己需求设置）
cd /www/wwwroot/目录  && php think BotTask

26.领取码任务符合条件用户自动领取
    cd /www/wwwroot/目录  && php think auto:getcode

27.代理报表数据 （状态：1昨天 2按日期获取，开始日期:2024-08-16, 结束日期:2024-08-25）
    cd /www/wwwroot/目录  && php think agentreport

28.游戏排行榜统计和结算 （5分钟）
cd /www/wwwroot/目录  && php think jackpot


### 队列任务
自动参与活动
```php
php think queue:work --queue=activity
```