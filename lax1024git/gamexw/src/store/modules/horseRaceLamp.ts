import { notice_list_api } from "@/api";
import { NoticeListType } from "@/enum/NoticeListType";
import ApiStorage from "@/storage/ApiStorage";
import { NoticeListApiItem } from "@/types/api";
import { defineStore } from "pinia";

export default defineStore("horseRaceLamp", {
  state: () => {
    return {
      data: [] as NoticeListApiItem[],
      loading: false,
    };
  },
  actions: {
    async getData() {
      // 有数禁止啦接口，避免重复请求
      if (this.data.length) return;
      const fetch = new ApiStorage({
        api: () =>
          notice_list_api({
            page: 1,
            limit: 10,
            type: NoticeListType.horseRaceLamp,
          }),
        success: (data) => {
          this.data = data;
        },
      });
      this.loading = true;
      await fetch.getData();
      this.loading = false;
    },
  },
});
