<template>
  <div>
    <CommonPop></CommonPop>
    <Announcement></Announcement>
    <router-view></router-view>
  </div>
</template>

<script setup lang="ts">
import CommonPop from "@/components/common/CommonPop.vue";
import useStore from "@/store";
import Announcement from "@/components/common/Announcement.vue";
import { onMounted, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { init as webSocketInit, wsServe } from "@/utils/websocket/webSocket";
import webSocketEventInit from "@/utils/websocket/webSocketEventInit";
import { openLink } from "./utils/openLink";
import { DeviceType } from "./enum/DeviceType";
import { createPixel } from "./utils/pixel/createPixel";
const $route = useRoute();
const $router = useRouter();
const { systemStore, gameTypeStore, musicStore, userStore, indexMenuStore, shareCodeStore, envStore, downloadTipStore } = useStore();


// 添加监听事件
systemStore.addEventIsPhone();

// 更新游戏类型
gameTypeStore.updateData();

// 初始化音乐
musicStore.init();

// 获取用户信息
userStore.getUserInfo();

// 获取菜单配置
indexMenuStore.getData();

// 注册websocket全局事件
webSocketEventInit();


// 监听登录状态做相关操作
watch(() => userStore.isLogin, async (v) => {
  if (v) {
    // 初始化webSocket
    await webSocketInit();
  } else {
    // 注销webSocket
    wsServe.close();
  }
}, { immediate: true });

// 初始化
onMounted(async () => {
  // 等待路由结束
  await $router.isReady();

  // 创建px像素代码
  createPixel($route.query.acid as string);

  // 由于后台链接是通用的，需要单独处理注册链接,非必要可以删除
  if ($route.query.modal === "LoginRegistration") openLink("/register");

  // 设置环境变量
  if ($route.query.env) envStore.setData($route.query.env as string);

  // 判断当前环境，是否显示app下载
  const dev = envStore.getDevice();
  if (dev !== DeviceType.iosApp && dev !== DeviceType.androidApp) {
    downloadTipStore.changeShow(true);
  }

  // 设置邀请码
  if ($route.query.share_code) shareCodeStore.setCode($route.query.share_code as string);

});

</script>

<style scoped lang="less"></style>
