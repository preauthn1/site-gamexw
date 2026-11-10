<template>
  <div class="page-box">
    <NavBar :title="$t('取回余额')" class="nav-bar"></NavBar>
    <div class="balance-box">
      <div class="total-balance-box">
        <div class="total-balance">
          <span>{{ $t("当前余额") }}</span>
          <span class="balance-num">0.00</span>
          <t-svg name="shuaxin" class="refresh-icon"></t-svg>
        </div>
        <el-button plain>{{ $t("一键恢复") }}</el-button>
      </div>
      <div class="balance-desc">{{ $t("您只能恢复余额的整个倍数（即没有小数点）") }}</div>
    </div>

    <PcTabs :tabs-list="pageList" class="page-content">
      <div class="content-box">
        <div class="search-box">
          <el-input size="large" class="round search" :suffix-icon="Search" :placeholder="$t('平台搜索')"></el-input>
          <el-button type="primary" size="large" class="search-btn">{{ $t("搜索") }}</el-button>
        </div>
        <Empty class="empty"></Empty>
      </div>
    </PcTabs>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import PcTabs from "@/components/common/PcTabs.vue";
import { Search } from "@element-plus/icons-vue";
import Empty from "@/components/common/Empty.vue";
const pageList = [
  { name: "All" },
  { name: "Commission" },
];
</script>

<style scoped lang="less" src="@/assets/css/pages/retrieveTheBalance.less"></style>