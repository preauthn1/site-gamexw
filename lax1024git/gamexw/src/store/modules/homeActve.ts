import router from "@/router";
import { defineStore } from "pinia";
export default defineStore("homeActve", {
  state: () => {
    return {
      active: 0,
      isWatchRouter: false,
    };
  },
  actions: {
    changeActive(index: number) {
      const currentRoute = router.currentRoute.value;
      this.isWatchRouter = true;
      router[currentRoute.path === "/home" ? "replace" : "push"](
        "/home?current=" + index
      );
      setTimeout(() => (this.isWatchRouter = false), 100);
    },
  },
});
