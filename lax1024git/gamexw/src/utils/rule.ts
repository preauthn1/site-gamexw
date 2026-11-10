import lang from "@/lang";
export const passwordRule = () => [
  {
    required: true,
    message: lang.t("密码6-20位字符"),
    trigger: "change",
    max: 20,
    min: 6,
  },
];

export const passwordAgainRule = (getPassword: () => string) => [
  {
    required: true,
    message: lang.t("密码6-20位字符"),
    trigger: "change",
    max: 20,
    min: 6,
  },
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (getPassword() !== value) {
        callback(new Error(lang.t("两次密码输入不一致")));
      }
      callback();
    },
    trigger: "change",
  },
];

export const usernameRule = () => [
  {
    required: true,
    message: lang.t("请输入邮箱"),
    trigger: "change",
  },
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        callback(new Error(lang.t("邮箱格式不正确")));
      }
      callback();
    },
    trigger: "change",
  },
];

export const phoneRule = () => [
  {
    required: true,
    message: lang.t("请输入手机号码"),
    trigger: "change",
  },
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (!/^\d+$/.test(value)) {
        callback(new Error(lang.t("手机号码格式不正确")));
      }
      callback();
    },
    trigger: "change",
  },
];

export const usernameAndPhoneRule = () => [
  {
    required: true,
    message: lang.t("请输入用户名"),
    trigger: "change",
  },
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (!/^\d+$/.test(value) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        callback(new Error(lang.t("用户名格式不正确")));
      }
      callback();
    },
    trigger: "change",
  },
];

export const phoneCodeRule = () => [
  {
    required: true,
    message: lang.t("请输入验证码"),
    trigger: "change",
  },
];

export const payPasswordRule = () => [
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (!/^\d{6}$/.test(value)) {
        callback(new Error(lang.t("密码6位数字")));
      }
      callback();
    },
    trigger: "change",
  },
];

export const payPasswordAgainRule = (getPassword: () => string) => [
  {
    validator: (
      _rule: unknown,
      value: string,
      callback: (err?: Error) => void
    ) => {
      if (!/^\d{6}$/.test(value)) {
        callback(new Error(lang.t("密码6位数字")));
      }
      if (getPassword() !== value) {
        callback(new Error(lang.t("两次密码输入不一致")));
      }
      callback();
    },
    trigger: "change",
  },
];
