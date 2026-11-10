import { index_menu_list_api } from "@/api";
import ApiStorage from "@/storage/ApiStorage";
import { IndexMenu } from "@/types/api";
import { defineStore } from "pinia";

export default defineStore("indexMenu", {
  state: () => {
    return {
      menuData: null as unknown as IndexMenu | null,
    };
  },
  actions: {
    async getData() {
      const fetch = new ApiStorage({
        api: () => index_menu_list_api(),
        success: (data) => {
          this.menuData = data;
        },
      });
      await fetch.getData();
    },
  },
});
