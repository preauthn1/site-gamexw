import { BankType } from "@/enum/BankType";
import { BankLists, BankMyItem } from "@/types/api/bank";
import { ValueOf } from "@/types/utils";
import { request } from "@/utils/request";

export const bank_lists_api = () => request.post<BankLists>("bank/lists");

export const bank_add_api = (data: {
  bank_type: ValueOf<typeof BankType>;
  bank_name: string;
  bank_number: string;
  bank_open?: string;
  bank_hm?: string;
  financial?: string;
  province?: string;
  city?: string;
  remarks?: string;
  currency_id?: string;
  google_code?: string;
  bind_bank_type?: string;
}) => request.post<BankLists>("bank/add", data);

export const bank_my_api = () => request.post<BankMyItem[]>("bank/my");
