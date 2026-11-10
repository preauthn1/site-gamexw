import { defineStore } from "pinia";
import { SystemInfoApi, SystemInfoNewApi } from "@/types/api";
import createMetaLink from "@/utils/createMetaLink";
export default defineStore("ststem", {
  state: () => {
    return {
      isPhone: window.innerWidth <= 768,
      systemData: null as SystemInfoApi | null,
      systemDataNew: null as SystemInfoNewApi | null,
    };
  },
  actions: {
    addEventIsPhone() {
      window.addEventListener("resize", () => {
        this.isPhone = window.innerWidth <= 768;
      });
    },
    upDateSystemData(data: SystemInfoApi) {
      this.systemData = data;
    },
    upDateSystemDataNew(data: SystemInfoNewApi) {
      this.systemDataNew = data;
      this.setHead();
    },

    // 设置header
    setHead() {
      if (this.systemDataNew) {
        createMetaLink("icon", this.systemDataNew?.site.website_favicon);
        createMetaLink(
          "apple-touch-icon-precomposed",
          this.systemDataNew.site.website_desktop_logo
        );
        createMetaLink(
          "apple-touch-icon",
          this.systemDataNew.site.website_desktop_logo
        );
        createMetaLink(
          "apple-mobile-web-app-title",
          this.systemDataNew.site.website_name
        );
        document.title = this.systemDataNew.site.website_name;
      }
    },
  },
});
