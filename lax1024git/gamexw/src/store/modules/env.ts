import { DeviceType } from "@/enum/DeviceType";
import BaseStorage from "@/storage/BaseStorage";
import { defineStore } from "pinia";
const env = new BaseStorage<string>("env", false);
export default defineStore("env", {
  state: () => {
    return {
      data: env.getData() || "",
    };
  },
  actions: {
    setData(data: string) {
      env.setData(data);
      this.data = data;
    },
    getDevice() {
      if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window["MSStream"]) {
        return window.navigator["standalone"] || this.data === "app"
          ? DeviceType.iosApp
          : DeviceType.iosWeb;
      } else if (navigator.userAgent.match(/Android/i)) {
        return window.navigator["standalone"] || this.data === "app"
          ? DeviceType.androidApp
          : DeviceType.androidWeb;
      } else if (navigator.userAgent.match(/Macintosh/i)) {
        return DeviceType.mac;
      } else if (navigator.userAgent.match(/Windows/i)) {
        return DeviceType.windows;
      } else {
        return DeviceType.other;
      }
    },
  },
});
