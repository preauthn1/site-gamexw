import axios, { AxiosInstance, AxiosResponse, AxiosError } from "axios";
import { Request as RequestType } from "@/types/api/request";
import { ResCode } from "@/enum/ResultCode";
import { ElMessage } from "element-plus";
import Token from "../storage/Token";
import router from "@/router";
import { openLink } from "./openLink";
import lang from "@/lang";
import getApiUrl from "./getApiUrl";
import useStore from "@/store";
export class Request {
  private service?: AxiosInstance;
  private headers?: Record<string, string>;
  constructor(data?: { baseURL?: string; headers?: Record<string, string> }) {
    this.headers = data?.headers || { "Content-Type": "application/json" };
    this.init(data?.baseURL || getApiUrl());
  }
  init(baseURL: string) {
    this.create(baseURL);
    this.requestSetConfig();
    this.responseBefor();
  }

  // create
  create(baseURL: string) {
    this.service = axios.create({
      baseURL,
      timeout: 60000,
      headers: this.headers,
    });
  }

  // 请求设置
  requestSetConfig() {
    this.service?.interceptors.request.use((config) => {
      const tokenData = Token.getData();
      if (tokenData) {
        config.headers["usertoken"] = tokenData.token;
        config.headers["usertokenkey"] = tokenData.token_key;
      }

      config.headers["lang"] = lang.locale;
      return config;
    });
  }

  // 数据拦截
  responseBefor() {
    this.service?.interceptors.response.use(
      this.callBackResponse,
      this.callBackError
    );
  }

  // 验证数据
  callBackResponse(response: AxiosResponse) {
    if (response.status === 200) {
      return response.data;
    }
  }

  testData(res: RequestType<unknown>, tips = true) {
    // token过期 返回首页
    if (res.code === ResCode.tokenExpired) {
      Token.removeData();
      router.push("/");
    } else if (res.code === ResCode.withdrawalPassword) {
      // 提现密码完善提示
      openLink("/confirm", {
        content:
          "检测到您的账号资料尚未完善，为保证您的资金账户安全，请完善您提现密码！",
        btnConfirmText: "去设置",
        confirm: () => {
          router.push("/withdrawalPass");
        },
      });
    } else if (res.code !== ResCode.success && tips) {
      ElMessage({
        type: "error",
        message: res.msg,
      });
    }
  }

  // 请求错误
  callBackError(error: AxiosError) {
    if (error.response?.status === 500) {
      return { code: 0, msg: lang.t("服务器异常，请联系管理员处理") };
    } else if (error.response?.status === 404) {
      return { code: 0, msg: lang.t("Api地址不存在") };
    } else {
      return { code: 0, msg: lang.t("网络请求异常，请稍后重试") };
    }
  }

  // get请求
  async get<T = unknown>(
    url: string,
    data?: Record<string, unknown>,
    tips = true
  ) {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    type Request = T extends RequestType<infer _U> ? T : RequestType<T>;
    if (!this.service) throw new Error("ajax未初始化完毕");
    const { shareCodeStore } = useStore();
    const res = (await this.service({
      url,
      method: "get",
      params: { ...data, share_code_global: shareCodeStore.code },
    })) as unknown as Request;
    this.testData(res, tips);
    return res;
  }

  // post请求
  async post<T = unknown>(
    url: string,
    data?: Record<string, unknown> | FormData,
    tips = true
  ) {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    type Request = T extends RequestType<infer _U> ? T : RequestType<T>;
    if (!this.service) throw new Error("ajax未初始化完毕");
    const { shareCodeStore } = useStore();
    const res = (await this.service({
      url,
      method: "post",
      data: { ...data, share_code_global: shareCodeStore.code },
    })) as unknown as Request;
    this.testData(res, tips);
    return res;
  }
}
export const request = new Request();
