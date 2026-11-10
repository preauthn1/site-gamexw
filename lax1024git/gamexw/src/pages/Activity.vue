<template>
  <div class="page-box">
    <PcTabs :tabsList="pageList" class="page-content">
      <router-view v-if="$route.path.startsWith('/promotion/activity')"></router-view>
    </PcTabs>
  </div>
</template>

<script setup lang="ts">
import PcTabs from "@/components/common/PcTabs.vue";
const pageList = [
  { name: "全部", url: "/promotion/activity/all" },
  { name: "历史", url: "/promotion/history" },
];
</script>

<style scoped lang="less" src="@/assets/css/pages/activity/activity.less"></style>