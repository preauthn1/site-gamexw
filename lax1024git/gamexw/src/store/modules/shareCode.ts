import BaseStorage from "@/storage/BaseStorage";
import { defineStore } from "pinia";
const shareCode = new BaseStorage<string>("share_code", false);

export default defineStore("shareCode", {
  state: () => {
    return {
      code: shareCode.getData() || "",
    };
  },
  actions: {
    setCode(code: string) {
      shareCode.setData(code);
      this.code = code;
    },
  },
});
