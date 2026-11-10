<template>
  <div class="withdrawRecords-box">
    <div class="top-input-box">
      <el-select size="large" v-model="select" class="round select">
        <el-option label="巴西雷亚尔" value="1" />
        <el-option label="+65" value="2" />
        <el-option label="+75" value="3" />
      </el-select>
      <div class="all-box">
        <span>{{ $t("待审核总额") }}</span>
        <span>R$0,00</span>
        <t-svg name="shuaxin" class="refresh-icon"></t-svg>
      </div>
    </div>
    <el-table :data="tableData" size="large" class="t-table">
      <el-table-column prop="bonus" :label="$t('时间')" />
      <el-table-column prop="bonus" :label="$t('类型')" />
      <el-table-column prop="bonus" :label="$t('数量')" />
      <el-table-column prop="bonus" :label="$t('状态')" />
      <el-table-column prop="bonus" :label="$t('行为')" />
    </el-table>
    <Empty class="empty"></Empty>
  </div>
</template>

<script setup lang="ts">
import Empty from "@/components/common/Empty.vue";
import { ref } from "vue";
const select = ref("1");
const tableData = [
  { level: 0, bet: 1000, bonus: 1000, levelIcon: "vip0.png" },
];
</script>

<style scoped lang="less" src="@/assets/css/pages/withdraw/auditReport.less"></style>