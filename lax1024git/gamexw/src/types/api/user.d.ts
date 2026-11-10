import { Request } from "./request";

export type UserInfo = {
  accumulate: string;
  add_time: number;
  balance: string;
  balance1: string;
  bankcard_check: number;
  birthday: string;
  cjbalance: string;
  count_interest: number;
  facebook: string | null;
  give_price: number;
  goodsle_bind: number;
  head_img: string;
  id: number;
  invalid_money: number;
  is_check_phone: number;
  ispass: number;
  lebal: number;
  level_id: number;
  level_image: string;
  level_name: string;
  limit_max: string;
  limit_min: string;
  limit_sum_max: string;
  live_fund_money: string;
  login_check: number;
  login_password_check: number;
  mail: string;
  mail2: string;
  mail_bind_status: number;
  next_level_id: number;
  next_level_name: string;
  next_level_rate: number;
  next_level_recharge: string;
  next_level_water: string;
  password: string;
  pay_passwd_verify: number;
  phone: string;
  phone_bind_status: number;
  phone_status: number;
  qh: string;
  real_name: string;
  rec_code: string;
  recharge: string;
  recharge1: number;
  savings: string;
  sc_date: number;
  telegram: string | null;
  terday: number;
  third_balance: number;
  third_sys_sign: string;
  tree: string;
  twitter: string | null;
  usdt: number;
  user_verify_status: number;
  username: string;
  valid_money: number;
  water: number;
  whatsapp: string | null;
  withdrawal_check: number;
  withdrawal_password_check: number;
  withdrawal_psd: boolean | string;
  yphone: string;
};

export type AvatarListItem = {
  id: number;
  name: string;
  url: string;
};

export type GoogleData = Request<string> & {
  secret: string;
};

export type VipListItem = {
  id: number;
  level_name: string;
  level_nickname: string;
  image: string;
  recharge_amount: string;
  min_amount: string;
  max_amount: string;
  day_limit: number;
  day_free_limit: number;
  level_gold: string;
  type: number;
  lock: number;
  first_user_level: number;
  first_user: number;
  team_user: number;
  count_order_amount: number;
  buy_amount: number;
  is_apply: number;
  fund_num: number;
  status: number;
  vip_condition: {
    key: string;
    value: string;
    plan: string;
    is_finish: boolean;
  }[];
  week_is_receive_vip_reward: number;
  month_is_receive_vip_reward: number;
  week_last_receive_time: number;
  month_last_receive_time: number;
  water: string;
  week_water: string;
  month_water: string;
  week_reward_amount: number;
  month_reward_amount: number;
  fy_rate1: number;
  fy_rate2: number;
  fy_rate3: number;
};

export type VipListData = Request<VipListItem[]> & {
  apply: number;
};

export type BalanceRecordItem = {
  id: number;
  new_price: string;
  price: string;
  type: string;
  add_time: string;
  child_type: string;
};
export type BalanceRecord = Request<BalanceRecordItem[]> & {
  type: string[];
};

export type WsToken = {
  WebSocket: string;
  token: string;
  uid: string;
};
