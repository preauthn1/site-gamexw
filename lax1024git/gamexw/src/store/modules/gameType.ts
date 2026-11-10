import { game_get_type_api } from "@/api/games";
import ApiStorage from "@/storage/ApiStorage";
import { GameTypeItem } from "@/types/api/games";
import { require } from "@/utils/$require";
import { defineStore } from "pinia";

export default defineStore("gameType", {
  state: () => {
    return {
      data: [] as GameTypeItem[],
      defData: [] as GameTypeItem[], //去掉前端添加的数据
      loading: false,
    };
  },
  actions: {
    async updateData() {
      const fetch = new ApiStorage({
        api: () => game_get_type_api(),
        success: (data) => {
          this.defData = data;
          this.data = [
            {
              name: "hot",
              icon: require("hot.png"),
              default_icon: "hot",
              tag: "hot",
            },
            ...data,
            {
              name: "收藏",
              icon: require("favorite.png"),
              default_icon: "favorite",
              id: "collect_id",
            },
          ];
        },
      });
      this.loading = true;
      await fetch.getData();
      this.loading = false;
    },
  },
});
