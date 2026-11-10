<template>
  <div class="box">
    <div class="card-box" v-if="systemStore.isPhone">
      <Tabs :shrink="true" class="tabs" :before-change="beforeChange" :active="active">
        <Tab :title="$t(item.name)" v-for="item in pageList" :key="item.name"></Tab>
      </Tabs>
    </div>
    <router-view v-slot="{ Component }" v-if="$route.path.startsWith('/promotion')">
      <keep-alive>
        <component :is="Component"></component>
      </keep-alive>
    </router-view>
  </div>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { Tab, Tabs } from "vant";
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
const { systemStore } = useStore();
const $route = useRoute();
const $router = useRouter();
const pageList = [
  { name: "活动", url: "/promotion/activity/all" },
  // { name: "使命", url: "/promotion/mission" },
  { name: "贵宾", url: "/promotion/vip" },
  // { name: "代办的", url: "/promotion/discount" },
  { name: "历史", url: "/promotion/history" },
];
const active = ref(0);
watch(() => $route.path, (v) => {
  active.value = pageList.findIndex(item => item.url === v);
}, { immediate: true });
const beforeChange = (i: number) => {
  $router.replace(pageList[i].url || "");
  return true;
};
</script>

<style scoped lang="less" src="@/assets/css/pages/promotion.less"></style>