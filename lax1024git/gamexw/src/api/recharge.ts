import {
  RechargeAll,
  RechargeApply,
  RechargeRecordItem,
} from "@/types/api/recharge";
import { request } from "@/utils/request";

export const recharge_get_all_api = () =>
  request.post<RechargeAll>("recharge/get_all");

export const recharge_apply_api = (data: {
  bank_sk_id?: number | string;
  pay_set_id?: number | string;
  not_auto_action?: number | string;
  apply_price: string | number;
  type: number | string;
  usdt_type?: number | string;
  credentials?: string;
}) => request.post<RechargeApply>("recharge/apply", data);

export const recharge_record_api = (data: {
  page: number | string;
  limit: number | string;
  start_time?: number | string;
  end_time?: number | string;
}) => request.post<RechargeRecordItem[]>("recharge/record", data);
