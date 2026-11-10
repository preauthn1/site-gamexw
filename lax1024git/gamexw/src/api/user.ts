import {
  AvatarListItem,
  BalanceRecord,
  GoogleData,
  UserInfo,
  VipListData,
  WsToken,
} from "@/types/api/user";
import { request } from "@/utils/request";

// 发送验证码
export const sendcode_index_api = (data: {
  scene: "bind" | "verify" | "forget" | "register";
  qh: string;
  phone: string;
}) => request.post("sendcode/index", data);

// 发送验证码
export const sendcode_mail_api = (data: {
  scene: "bind" | "verify" | "forget" | "register";
  email: string;
}) => request.post("sendcode/mail", data);

// 个人信息
export const my_info_v5_api = () => request.post<UserInfo>("my/info_v5");

// 修改个人信息
export const my_editinfo_api = (
  data: Partial<UserInfo> & {
    verify_code?: string;
    old_password?: string;
    owithdrawal_psd?: string;
  },
  tips = true
) => request.post("my/editinfo", data, tips);

// 头像列表
export const user_get_default_head_img_api = () =>
  request.post<AvatarListItem[]>("user/get_default_head_img");

// 谷歌身份验证器
export const my_google_api = () => request.post<GoogleData>("my/google");

// 绑定谷歌验证
export const my_bind_google_api = (data: { code: string }) =>
  request.post("my/bind_google", data);

//支付密码
export const my_pay_password_api = (
  data: {
    opassword: string;
    password: string;
    verify_code?: string;
  },
  tips = true
) => request.post("my/pay_password", data, tips);

// 绑定谷歌验证
export const user_vip_api = () => request.post<VipListData>("user/vip");

// 用户等级奖励领取
export const user_receiveLevelReward_api = (data: {
  reward_type: number | string;
  level_id: number | string;
}) => request.post("user/receiveLevelReward", data);

// 余额记录
export const my_balanceRecord_api = (data: {
  page: number | string;
  limit: number | string;
  type?: string;
  id?: number | string;
  start_time?: number | string;
  end_time?: number | string;
}) => request.post<BalanceRecord>("my/balanceRecord", data);

// 邮箱重置密码
export const forget_mail_api = (data: {
  mail: string;
  mail_code: string;
  password: string;
}) => request.post("forget/mail", data);

export const forget_phone_pwd = (data: {
  phone: string;
  code: string;
  password: string;
}) => request.post("forget/pwd", data);

// 获取socket信息
export const user_get_ws_token_api = () =>
  request.post<WsToken>("user/get_ws_token");
