import { createApp } from "vue";
import App from "./App.vue";
import "@/assets/css/base.less";
import "@/assets/css/var.less";
import "element-plus/theme-chalk/src/message.scss";
import "element-plus/theme-chalk/src/loading.scss";
import "element-plus/theme-chalk/src/notification.scss";
import router from "./router";
import TSvg from "@/components/common/TSvg.vue";
import "virtual:svg-icons-register";
import "@/assets/css/element-ui.less";
import "@/utils/setVh.ts";
import "@/utils/addWindowCalcColor.ts";
import "@/utils/theme.ts";
import "vant/lib/index.css";
import { require, Require } from "@/utils/$require";
import { openLink, OpenLink } from "@/utils/openLink";
import { register } from "swiper/element/bundle";
import { createPinia } from "pinia";
import Empty from "@/components/common/Empty.vue";
import { getData, getStorageData } from "./utils/appInit";
import lang from "./lang";
import launchPage from "@/components/page/launchPage";
import numInit from "./utils/numInit";
import copy from "./utils/copy";
import { dataBase } from "./utils/databaseBusiness";
import DataBaseStorage from "./storage/DataBaseStorage";
import { popRouterInit } from "@/router/popRouter";

const pinia = createPinia();
const app = createApp(App);
register();

// 注册全局组件
app.component("TSvg", TSvg);
app.component("Empty", Empty);

// 注册全局函数
declare module "@vue/runtime-core" {
  export interface ComponentCustomProperties {
    $require: Require;
    $t: typeof lang.t;
    $openLink: OpenLink;
    $numInit: typeof numInit;
    $copy: typeof copy;
  }
}
app.config.warnHandler = () => {};
app.config.globalProperties.$require = require;
app.config.globalProperties.$openLink = openLink;
app.config.globalProperties.$t = lang.t.bind(lang);
app.config.globalProperties.$numInit = numInit;
app.config.globalProperties.$copy = copy;
app.use(pinia);
app.use(router);

(async () => {
  popRouterInit();
  await dataBase.open();
  await DataBaseStorage.init();
  if (await getStorageData()) {
    getData();
  } else {
    // 没有缓存异步加载数据，卡主先不渲染
    launchPage.show();
    await getData();
  }
  app.mount("#app");
  launchPage.hide();
})();