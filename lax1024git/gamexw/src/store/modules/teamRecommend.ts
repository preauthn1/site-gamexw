import { v2_team_recommend_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { TeamRecommend } from "@/types/api/team";
import { defineStore } from "pinia";

export default defineStore("teamRecommend", {
  state: () => {
    return {
      data: null as unknown as TeamRecommend | null,
    };
  },
  actions: {
    async getData() {
      const res = await v2_team_recommend_api();
      if (res.code === ResCode.success) {
        this.data = res.data;
      }
    },
  },
});
