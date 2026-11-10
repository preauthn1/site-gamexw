import { TurntableType } from "@/enum/TurntableType";
import { ValueOf } from "../utils";
export type Lotteryconfig = {
  id: number;
  rule: string;
  starttime: string;
  endtime: string;
  status: number;
  free_count: number;
  last_lottery_time: number;
  required_balance: string;
  limit_count: number;
  title: string;
  max_award: string;
  theme_type: ValueOf<typeof TurntableType>;
  balance: string;
  integral: number;
  required_integral: number;
  Prize: {
    id: number;
    type: number;
    pic: string;
    title: string;
    amount: string;
    stock: number;
    probability: string;
  }[];
};

export type LotteryrecordItem = {
  id: number;
  uid: number;
  prize_id: number;
  title: string;
  time: string;
  prize_type: number;
  price: string;
  prize_price: string;
  is_free: number;
  username: string;
  pic: string
};

export type ActionItem = {
  auto_apply: number;
  base_name: string | null;
  base_type: number;
  c_detail: string;
  c_img: string;
  c_intro: string;
  c_label: string;
  c_title: string;
  date_type: number;
  dis_code: string;
  id: number;
  is_hot: number;
  jump_url: string;
  t_apply_end_time: string;
  t_apply_start_time: string;
  type_id: number;
};
