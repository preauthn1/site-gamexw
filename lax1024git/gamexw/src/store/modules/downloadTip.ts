import { defineStore } from "pinia";
export default defineStore("downloadTip", {
  state: () => {
    return {
      isShow: false,
    };
  },
  actions: {
    changeShow(isShow: boolean) {
      this.isShow = isShow;
    },
  },
});
