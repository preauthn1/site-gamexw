export type WithdrawLimit = {
  day_limit: number;
  is_withdrawal_psd: number;
  max_amount: string;
  min_amount: string;
  now_water_balance: string;
  pay_passwd_verify: number;
  service_charge: number;
  usdt_charge: number;
  user_withdrawal_amount: number;
  user_withdrawal_amount_l: (number | string)[];
  ver_withdrawal_first: number;
  withdrawal_rules: number;
  old_max_amount: string;
  old_min_amount: string;
};

export type WithdrawRecordItem = {
  id: number;
  order_sn: string;
  channel: number;
  w_price: string;
  arrival_price: string;
  arrival_prices: string;
  w_service: string;
  w_remarks: string;
  status: number;
  update_time: string;
  add_time: string;
  bank_number: string;
  bank_name: string;
  bankcrad: string;
};
