import { bank_my_api } from "@/api/bank";
import ApiStorage from "@/storage/ApiStorage";
import { BankMyItem } from "@/types/api/bank";
import { defineStore } from "pinia";

export default defineStore("bank", {
  state: () => {
    return {
      bankList: [] as BankMyItem[],
    };
  },
  actions: {
    async getList(isGet = true) {
      // isGet为false  直接强拉数据，否则判断有就不重复拉取
      if (this.bankList.length && isGet) return;
      const fetch = new ApiStorage({
        api: () => bank_my_api(),
        success: (data) => {
          this.bankList = data;
        },
      });
      await fetch.getData();
    },
  },
});
