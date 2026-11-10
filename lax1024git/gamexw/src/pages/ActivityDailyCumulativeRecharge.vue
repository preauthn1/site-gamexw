<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('每日累计充值额外奖励')">
      <template #right>
        <div>
          <el-button type="primary" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
          <el-button type="primary" :disabled="true" class="m-hide">{{ $t("收集一切") }}</el-button>
        </div>
      </template>
    </NavBar>
    <div class="tip">{{ $t("目前没有奖励可供领取") }}</div>
    <div class="tabel-box">

      <el-table :data="tableData" size="large" class="t-table">
        <el-table-column prop="bonus" :label="$t('存款总额')"></el-table-column>
        <el-table-column prop="bonus" :label="$t('奖励价值')"></el-table-column>
      </el-table>
    </div>
    <div class="rule-box">
      <div class="rule-title">{{ $t("活动详情") }}</div>
      <div class="rule-content">{{ $t("每日累计充值额外奖励详情介绍") }}</div>
    </div>
    <AllActivity></AllActivity>
    <div class="foot pc-hide">
      <div class="foot-content">
        <el-button class="full" size="large" plain @click="$router.back">{{ $t("后退") }}</el-button>
        <el-button type="primary" class="full" size="large">{{ $t("收集一切") }}</el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import AllActivity from "@/components/common/AllActivity.vue";
const tableData = [
  { level: 0, bet: 1000, bonus: 1000, levelIcon: "vip0.png" },
  { level: 1, bet: 1000, bonus: 1000, levelIcon: "vip1.png" },
  { level: 2, bet: 1000, bonus: 1000, levelIcon: "vip2.png" },
  { level: 3, bet: 1000, bonus: 1000, levelIcon: "vip3.png" },
  { level: 4, bet: 1000, bonus: 1000, levelIcon: "vip4.png" },
  { level: 5, bet: 1000, bonus: 1000, levelIcon: "vip5.png" },
  { level: 6, bet: 1000, bonus: 1000, levelIcon: "vip6.png" },
  { level: 7, bet: 1000, bonus: 1000, levelIcon: "vip7.png" },
  { level: 8, bet: 1000, bonus: 1000, levelIcon: "vip8.png" },
  { level: 9, bet: 1000, bonus: 1000, levelIcon: "vip9.png" },
  { level: 10, bet: 1000, bonus: 1000, levelIcon: "vip10.png" },
];
</script>

<style scoped lang="less" src="@/assets/css/pages/activityDailyCumulativeRecharge.less"></style>