import { my_logout_api } from "@/api";
import Token from "../storage/Token";
import { openLink } from "./openLink";
import router from "@/router";
import { ResCode } from "@/enum/ResultCode";
export default () => {
  openLink("/confirm", {
    content: "是否确认退出登录？",
    btnConfirmText: "确认退出",
    confirm: async () => {
      const res = await my_logout_api();
      if (res.code === ResCode.success) {
        Token.removeData();
        router.push("/");
      }
    },
  });
};
