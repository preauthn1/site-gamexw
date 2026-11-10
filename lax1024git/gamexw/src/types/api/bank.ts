import { BankType } from "@/enum/BankType";
import { ValueOf } from "../utils";

type CurrencyItem = {
  country_code: string;
  currency_code: string;
  id: number;
  is_defa: number;
  link_name: [];
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
export type BankLists = {
  all_currency: CurrencyItem[];
  bank_list: {
    bank_code: string;
    bank_field: string[];
    bank_name: string;
    orderby: string;
  }[][];
  currency: {
    defa_data: CurrencyItem;
    ordinary_data: CurrencyItem;
    usdt_data: CurrencyItem;
  };
  paytype: string[];
  pix_type: Record<string, string>;
  usdt_type: Record<string, string>;
};

export type BankMyItem = {
  bank_hm: string;
  bank_id: number;
  bank_name: string;
  bank_number: string;
  bank_open: string;
  bank_type: ValueOf<typeof BankType>;
  bankcrad: string;
  city: string;
  currency_code: string;
  currency_id: number;
  id: number;
  province: string;
  recharge_rate: string;
  remarks: string;
  withdrawal_rate: string;
};
