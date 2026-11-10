import { request } from "@/utils/request";

// 发送验证码
export const code_receive_api = (data: { code: string }) => {
  return request.post("code/receive", data);
};
