import { SignData } from "@/types/api/sign";
import { request } from "@/utils/request";

//  获取签到数据
export const sign_getSign_api = () => request.post<SignData>("sign/getSign");

// 签到
export const sign_userSign_api = (data: {
  sign_id: number | string;
  day: number | string;
}) => request.post<SignData>("sign/userSign", data);

// 补签
export const sign_addSign_api = (data: {
  sign_id: number | string;
  day: number | string;
}) => request.post<SignData>("sign/addSign", data);
