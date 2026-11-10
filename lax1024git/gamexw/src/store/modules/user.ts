import { my_info_v5_api } from "@/api/user";
import { ResCode } from "@/enum/ResultCode";
import Token from "@/storage/Token";
import { UserInfo } from "@/types/api/user";
import { defineStore } from "pinia";

export default defineStore("user", {
  state: () => {
    return {
      isLogin: Boolean(Token.getData()),
      userInfo:null as UserInfo | null
    };
  },
  actions: {
    changeLogin(e: boolean) {
      this.isLogin = e;
      if(e){
        this.getUserInfo();
      }else{
        this.userInfo = null;
      }
    },
    async getUserInfo(){
      // 没有登录不请求
      if(!this.isLogin) return;
      const res = await my_info_v5_api();
      if(res.code===ResCode.success){
        this.userInfo = res.data;
      }
    }
  },
});
