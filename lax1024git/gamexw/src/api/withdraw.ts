import { WithdrawLimit, WithdrawRecordItem } from "@/types/api/withdraw";
import { request } from "@/utils/request";

export const withdraw_limit_api = () =>
  request.post<WithdrawLimit>("withdraw/limit");

export const withdraw_apply_api = (data: {
  bank_id?: string | number;
  w_price?: string | number;
  first_withdrawal_code?: string;
  phone?: string | number;
  verify_code?: string;
}) => request.post("withdraw/apply", data, false);

export const withdraw_record_api = (data: {
  page: number | string;
  limit: number | string;
  start_time?: number | string;
  end_time?: number | string;
}) => request.post<WithdrawRecordItem[]>("withdraw/record", data);
