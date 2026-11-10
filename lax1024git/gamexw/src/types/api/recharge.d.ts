import { RechargeType } from "@/enum/RechargeType";
import { ValueOf } from "../utils";

export type RechargeAll = {
  action_is_force_join: number;
  action_list: {
    base_apply_dr_max: number;
    everyday_person_num: number;
    id: number;
    news_images: string;
    news_title: string;
    reg_json: {
      bl_or_amount: string;
      integral_amount: string;
      max_reg: string;
      min_reg: string;
      type: string;
    }[];
  }[];
  bank_payment_system: {
    name: string;
    src: string;
  }[];
  currency_code: string;
  currency_defa: number;
  currency_symbol: string;
  currency_type: string;
  finish_water: number;
  hide_rate: string;
  offline: {
    bank_khm: string;
    bank_name: string;
    bank_number: string;
    bank_open: string;
    bank_qrcode: string;
    currencyApi: {
      country_code: string;
      currency_code: string;
      currency_symbol: string;
      id: number;
      is_defa: number;
      link_name: string[];
      name: string;
      recharge_rate: string;
      state: number;
      symbol: string;
      symbol_img: string;
      type: {
        text: string;
        val: number;
      };
      withdrawal_rate: string;
    };
    currency_id: number;
    hide_rate: number;
    icon: string;
    id: number;
    is_all: number;
    is_hot: number;
    max_amcount: string;
    min_amcount: string;
    pay_type: number;
    type: ValueOf<typeof RechargeType>;
  }[];
  online: {
    currencyApi: {
      country_code: string;
      currency_code: string;
      currency_symbol: string;
      id: number;
      is_defa: number;
      link_name: string[];
      name: string;
      recharge_rate: string;
      state: number;
      symbol: string;
      symbol_img: string;
      type: {
        text: string;
        val: number;
      };
      withdrawal_rate: string;
    };
    currency_id: number;
    hide_rate: number;
    icon: string;
    id: number;
    is_all: number;
    is_hot: number;
    pay_max_amout: string;
    pay_min_amout: string;
    pay_name: string;
    pay_sys_sign: string;
    pay_type: number;
    type: ValueOf<typeof RechargeType>;
    usdt_type: string;
  }[];
  recharge_action_reg_json: {
    bl_or_amount: string;
    integral_amount: string;
    max_reg: string;
    min_reg: string;
    recharge_image: string;
    type: string;
  }[];
  recharge_amount_discount: {
    type: string;
    amount: string;
    bl_or_amount: string;
  }[];
  recharge_amount_sitting: {
    bank_recharge_list: string[];
    hot_bank_recharge_list: string[];
    usdt_recharge_list: string[];
  };
  recharge_rate: number;
  user_appoint_savings: {
    finish_water: number;
    game_name: string[];
    water: number;
  };
  water: number;
  withdrawal_rate: number;
};

export type RechargeApply = {
  pay_url: string;
  handle_type: string;
  is_qrcode: number;
  qrcode_url: string;
  address: string;
};

export type RechargeRecordItem = {
  type: number;
  channel: number;
  add_time: string;
  apply_price: string;
  arrival_price: string;
  order_sn: string;
  update_time: string;
  status: number;
  remarks: string | null;
};
