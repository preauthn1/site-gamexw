import {
  ActionItem,
  Lotteryconfig,
  LotteryrecordItem,
} from "@/types/api/action";
import { request } from "@/utils/request";
import { ElementType } from "@/types/utils";

// 活动列表
export const action_list_api = (data: {
  page: number | string;
  limit: number | string;
}) => request.post<ActionItem[]>("action/list", data);

// 活动详情
export const action_detail_api = (data: { action_id: number | string }) =>
  request.post<ActionItem>("action/detail", data);

// 活动申请
export const action_apply_api = (data: { action_id: number | string }) =>
  request.post("action/apply", data);

// 转盘列表
export const action_lotteryconfiglist_api = () =>
  request.post<Lotteryconfig[]>("action/lotteryconfiglist");

// 抽奖
export const action_lottery_api = (data: { id?: number }) =>
  request.post<ElementType<Lotteryconfig["Prize"]>>("action/lottery", data);

// 抽奖记录
export const action_lotteryrecord_api = (data: { id: number; page: number; limit: number; state: number }) =>
  request.post<{ data: LotteryrecordItem[], count: number }>("action/lotteryrecord", data);
