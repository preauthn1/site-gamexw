<template>
  <div class="download-box" v-if="downloadTipStore.isShow">
    <div class="close-box" @click="downloadTipStore.changeShow(false)">
      <t-svg name="close" class="close-icon"></t-svg>
    </div>
    <img :src="systemStore.systemDataNew?.site.website_desktop_logo" class="logo">
    <div class="desc">{{ $t("下载应用，随时玩耍") }}</div>
    <el-button type="warning" @click="downloadApp">{{ $t("立即下载App") }}</el-button>
  </div>
</template>

<script setup lang="ts">
import useStore from "@/store";
import downloadApp from "@/utils/downloadApp";
const { downloadTipStore, systemStore } = useStore();
</script>

<style scoped lang="less" src="@/assets/css/pages/index/downTips.less"></style>