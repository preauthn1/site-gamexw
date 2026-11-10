import { Request } from "./request";
export type Team = {
  commission: {
    today: number;
    total: number;
    yesterday: number;
  };
  invite_bonus: string;
  invite_url: string;
  rec_code: string;
  referral_reward: {
    today: number;
    total: number;
    yesterday: number;
  };
  team_people: {
    child: number;
    other: number;
    total: number;
  };
  team_statistics: {
    bet_child_user_amount: number;
    bet_total_amount: number;
    child_other_recharge_total_people: number;
    child_recharge_total_people: number;
    deposit_child_user: number;
    first_deposit_child_user: number;
    recharge_total_amount: number;
  };
  total_earnings: {
    today: number;
    total: number;
    yesterday: number;
  };
  without_rate: string[];
};

export type Remuneration = {
  current_symbol: string;
  list: {
    agent_ids: string;
    icon: string;
    id: number;
    is_check: number;
    is_finish: number;
    is_receive: number;
    name: string;
    receive_agent_type: number;
    reset_type: number;
    reward_type: number;
    savings: number;
    start_time: number;
    stratum: number;
    stratum_bonus: string;
    stratum_number: number;
  }[];
  total_num: number;
  valid_bet: number;
  valid_recharge: number;
};

export type TeamUser = {
  list: {
    id: number;
    username: string;
    status: number;
    balance: string;
    level: number;
    level_id: number;
    total_offline: number;
    is_effective: number;
  }[];
  user_count1: number;
  user_count2: number;
  user_count3: number;
  user_sum_money1: number;
  user_sum_money2: number;
  user_sum_money3: number;
  user_sum_money: number;
  user_badge: number;
  recharge_user_count: number;
  register_user_count: number;
};

export type RechargeRebate = {
  parent_rec_code?: string;
  people_num: number;
  invite_rebate: number;
  total_rebate_amount: number;
  invite_url: string;
  invite_recharge_rebate_list: {
    id: number;
    stratum: number;
    stratum_number: number;
    stratum_bonus: string;
  };
};

export type TeamCommissionItem = {
  sff_uid: number;
  sff_bet: string;
  sff_fy: string;
  sff_statstime: number;
  sff_utype: number;
  time: string;
};

export type TeamBackItem = {
  sb_dt: number;
  sb_uid: number;
  sb_backbase: string;
  sb_backmoney: string;
  time: string;
};

export type TeamRecord = {
  data: {
    create_time: string;
    money: string;
    zd_number: string;
    remark: string;
    status: number;
  }[];
  sum: number;
  count: number;
  settled: number;
  no_settled: number;
};

export type TeamRechargelistItem = {
  id: number;
  uid: number;
  apply_price: string;
  order_sn: string;
  remarks: string;
  add_time: string;
  username: string;
};

export type TeamCashlistItem = {
  id: number;
  uid: number;
  w_price: string;
  w_service: string;
  w_remarks: string;
  add_time: string;
  username: string;
};

export type TeamOrderlistItem = {
  id: number;
  uid: number;
  add_time: string;
  zd_number: string;
  tz_price: string;
  sy_res: string;
  status: number;
  username: string;
  money_type: number;
};

export type TeamRecommend = {
  invite_code: string; // 邀请码
  parent_code: string; // 上级邀请码
  total_commission: number; // 佣金
  invite_url: string; // 邀请链接
  commission: {
    total_commission: number; // 总佣金
    received_commission: number; // 已领取佣金
    last_commission: number; // 最近一次佣金
  };
  income: {
    child_user_num: number; // 直属下级用户数
    total_bet: number; // 团队投注总额
    child_bet: number; // 直属用户投注总额
    other_bet: number; // 其他用户的投注总额
  };
  child_statistics: {
    child_bet: number; // 直属用户投注总额
    child_bet_num: number; // 投注笔数
  };
};

// 我的佣金
export type V2TeamCommissionItem = {
  id: number;
  time: string; // 时间
  valid_bet: number; // 总流水
  commission: number; // 佣金
  user_num: number; // 人数
};
export type V2TeamCommission = Request<
  {
    id: number;
    time: string; // 时间
    valid_bet: number; // 总流水
    commission: number; // 佣金
    user_num: number; // 人数
  }[]
> & {
  commission: number;
};

// 佣金详情
export type V2TeamCommissionDetailItem = {
  mail: string; // 邮箱
  username: string; // 用户名
  phone: string; // 号码，优先显示号码，没有则显示邮箱，最后则是用户名
  sffl_dt: string; // 结算日期
  valid_bet: number; // 有效流水
  commission: number; // 佣金
};

// 我的收入
export type V2TeamIncomeItem = {
  username: string; // 用户名
  mail: string; // 邮箱
  phone: string | null; // 号码，可以为null
  add_time: string; // 添加时间
  valid_bet: number; // 有效流水
  commission: number; // 佣金
  child_user_num: number; // 直属下级用户数
};

// 所有数据
export type V2TeamStatisticsItem = {
  username: string; // 用户名
  phone: string | null; // 号码，可以为null
  mail: string; // 邮箱
  add_time: string; // 注册时间
  total_recharge: number; // 总充值
  total_bet: number; // 总投注
};
export type V2TeamStatistics = Request<V2TeamStatisticsItem[]> & {
  statistics: {
    child_recharge: number; // 直属充值总额
    child_user_num: number; // 直属首充人数
    other_recharge: number; // 其他累积总充值
    other_first_user_num: number; // 其他首充人数
    total_recharge: number; // 总充值累积总额
    total_first_user_num: number; // 总首充人数
  };
};

// 下级投注
export type V2TeamChildBetItem = {
  username: string; // 用户名
  phone: string | null; // 号码，可以为null
  mail: string; // 邮箱
  add_time: string; // 注册时间
  bet_num: number; // 投注数量
};

// 直属会员
export type V2TeamChildMemberItem = {
  id: number; // 用户ID
  username: string; // 用户名
  phone: string | null; // 号码，可以为null
  mail: string; // 邮箱
  add_time: string; // 注册时间
  status: 0 | 1; // 状态（1正常，0冻结）
  is_recharge: 0 | 1; // 是否充值（1是，0否）
};

// 直属奖金
export type V2TeamChildBonusItem = {
  username: string; // 用户名
  phone: string | null; // 号码，可以为null
  mail: string; // 邮箱
  add_time: string; // 注册时间
  total_bonus: number; // 总奖金
  action_bonus: number; // 活动奖金
  back_bonus: number; // 反水
  commission_bonus: number; // 返佣
};

// 返佣比例
export type V2TeamCommissionRateItem = {
  level: number; // 等级，例如：一级返佣
  rate: string; // 比例
};
