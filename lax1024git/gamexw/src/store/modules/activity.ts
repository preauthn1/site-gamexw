import { action_list_api } from "@/api/action";
import ApiStorage from "@/storage/ApiStorage";
import { ActionItem } from "@/types/api/action";
import { defineStore } from "pinia";

export default defineStore("activity", {
  state: () => {
    return {
      activityList: [] as ActionItem[],
      loading: false,
    };
  },
  actions: {
    async getList() {
      if (this.activityList.length || this.loading) return;
      this.loading = true;
      await new ApiStorage({
        api: () => action_list_api({ page: 1, limit: 70 }),
        success: (data) => {
          this.activityList = data;
        },
      }).getData();
      this.loading = false;
    },
  },
});
