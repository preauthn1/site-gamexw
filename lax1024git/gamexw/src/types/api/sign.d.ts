export type SignData = {
  id: number;
  surplus_sign_count: number;
  currency: string;
  current_day: number;
  day_list: {
    integral: string;
    day: number;
    reward_mode: number;
    reward_type: number;
    reward_amount: string;
    reward_integral: string;
    savings: number;
    status: number;
    amount: string;
  }[];
  total_amount: number;
  new_date: string;
  introduce: string;
  sign_day: number;
  min_recharge: number;
  min_bet: number;
  current_recharge: number;
  current_bet: number;
};
