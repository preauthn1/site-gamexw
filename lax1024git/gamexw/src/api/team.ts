import {
  RechargeRebate,
  Remuneration,
  Team,
  TeamBackItem,
  TeamCashlistItem,
  TeamCommissionItem,
  TeamOrderlistItem,
  TeamRechargelistItem,
  TeamRecommend,
  TeamRecord,
  TeamUser,
  V2TeamChildBetItem,
  V2TeamChildBonusItem,
  V2TeamChildMemberItem,
  V2TeamCommission,
  V2TeamCommissionDetailItem,
  V2TeamCommissionRateItem,
  V2TeamIncomeItem,
  V2TeamStatistics,
} from "@/types/api/team";
import { request } from "@/utils/request";

// 团队信息
export const team_view_api = () => request.post<Team>("team/view");

// 报酬列表
export const team_getRemuneration_api = () =>
  request.post<Remuneration>("team/getRemuneration");

// 报酬领取
export const team_receiveInviteRebate_api = (data: { id: number | string }) =>
  request.post("team/receiveInviteRebate", data);

// 团队列表
export const team_user_api = (data: {
  page: number;
  limit: number;
  level?: number | string;
  isday?: number;
  date?: string;
  username?: string;
}) => request.post<TeamUser>("team/user", data);

// 获取充值返利
export const team_getRechargeRebate_api = () =>
  request.post<RechargeRebate>("team/getRechargeRebate");

// 佣金列表
export const team_commission_api = (data: {
  page: number;
  limit: number;
  start_time?: string;
  end_time?: string;
}) => request.post<TeamCommissionItem[]>("team/commission", data);

// 反水列表
export const team_back_api = (data: {
  page: number;
  limit: number;
  start_time?: string;
  end_time?: string;
}) => request.post<TeamBackItem[]>("team/back", data);

// 佣金记录
export const team_record_api = (data: {
  page: number;
  limit: number;
  date?: string;
  is_stat?: number;
  isday?: number;
}) => request.post<TeamRecord>("team/record", data);

// 充值列表
export const team_rechargelist_api = (data: {
  page: number;
  limit: number;
  date?: string;
  isday?: number;
  level?: number | string;
}) => request.post<TeamRechargelistItem[]>("team/rechargelist", data);

// 提现列表
export const team_cashlist_api = (data: {
  page: number;
  limit: number;
  date?: string;
  isday?: number;
  level?: number | string;
}) => request.post<TeamCashlistItem[]>("team/cashlist", data);

// 注单列表
export const team_orderlist_api = (data: {
  page: number;
  limit: number;
  date?: string;
  isday?: number | string;
  level?: number | string;
  settlement?: number | string;
}) => request.post<TeamOrderlistItem[]>("team/orderlist", data);

export const v2_team_recommend_api = () =>
  request.get<TeamRecommend>("v2/team/recommend");

// 我的佣金
export const v2_team_commission_api = (data: {
  page: number;
  limit: number;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamCommission>("v2/team/commission", data);

// 佣金详情
export const v2_team_commissionDetail_api = (data: {
  page: number;
  limit: number;
  id?: string | number;
}) =>
  request.post<V2TeamCommissionDetailItem[]>("v2/team/commissionDetail", data);

// 我的收入
export const v2_team_income_api = (data: {
  page: number;
  limit: number;
  username?: string;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamIncomeItem[]>("v2/team/income", data);

// 所有数据
export const v2_team_statistics_api = (data: {
  page: number;
  limit: number;
  username?: string;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamStatistics>("v2/team/statistics", data);

// 下级投注
export const v2_team_childBet_api = (data: {
  page: number;
  limit: number;
  username?: string;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamChildBetItem[]>("v2/team/childBet", data);

// 直属会员
export const v2_team_childMember_api = (data: {
  page: number;
  limit: number;
  username?: string;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamChildMemberItem[]>("v2/team/childMember", data);

// 直属奖金
export const v2_team_childBonus_api = (data: {
  page: number;
  limit: number;
  username?: string;
  start_time?: string;
  end_time?: string;
}) => request.post<V2TeamChildBonusItem[]>("v2/team/childBonus", data);

// 返佣比例
export const v2_team_commissionRate_api = () =>
  request.post<V2TeamCommissionRateItem[]>("v2/team/commissionRate");

// 直属账号
export const v2_team_childAccount_api = (data: {
  username: string;
  password: string;
}) => request.post("v2/team/childAccount", data);
