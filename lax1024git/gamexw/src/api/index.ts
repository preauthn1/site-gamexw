import {
  SystemInfoApi,
  SystemInfoNewApi,
  BannerIndexApi,
  LoginApi,
  NoticeListApiItem,
  IndexMenu,
  Rule,
  SystemChannel,
} from "@/types/api";
import { request } from "@/utils/request";

// 系统配置
export const system_info_api = () => request.get<SystemInfoApi>("system/info");

// 系统配置新
export const system_info_new_api = () =>
  request.get<SystemInfoNewApi>("system/info_new");

// 获取轮播图
export const banner_index_api = () =>
  request.get<BannerIndexApi>("banner/index");

// 登录
export const login_login_api = (data: {
  username: string;
  password?: string;
  code?: string;
  qh?: string;
  account_type?: number;
  device_type?: string;
}) => request.post<LoginApi>("login/login", { ...data, code_key: "" });

// 注册
export const login_register_api = (data: {
  username: string;
  password?: string;
  code?: string;
  phone?: string;
  qh?: string;
  account_type?: number;
  rec_code?: string;
  device_type?: string;
  acid?: string | number;
}) =>
  request.post<LoginApi>("login/register", {
    ...data,
    code_key: "",
  });

// 登录
export const my_logout_api = () => request.post("my/logout");

// 获取公告
export const notice_list_api = (data: {
  page: number;
  limit: number;
  type: number;
}) =>
  request.post<NoticeListApiItem[]>("notice/list", { ...data, show_loca: 1 });

// 获取菜单
export const index_menu_list_api = () =>
  request.post<IndexMenu>("v2/index/menu_list");

// 获取协议
export const rule_index_api = (data: { type: number | string }) =>
  request.post<Rule>("rule/index", data);

// 获取埋点配置
export const system_channel_api = (data: { acid: number | string }) =>
  request.get<SystemChannel>("system/channel", data);
