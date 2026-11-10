export enum ResCode {
  success = 1,
  error = 500, //错误
  login = 20,
  tokenExpired = 1000, //token过期
  verificationCode = 120, // 需要验证码
  googleCode = 110, //需要谷歌验证器
  withdrawalPassword = 130, //需要设置支付密码
}
