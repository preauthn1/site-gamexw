import { defineStore } from "pinia";

export default defineStore("menu", {
  state: () => {
    return {
      isShow: window.innerWidth > 768,
    };
  },
  actions: {
    changeShow() {
      this.isShow = !this.isShow;
    },
  },
});
