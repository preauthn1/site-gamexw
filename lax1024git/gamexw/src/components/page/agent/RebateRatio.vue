<template>
  <div class="card-box">
    <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>

    <el-table :data="list" size="large" class="t-table" v-else>
      <el-table-column :label="$t('返佣等级')">
        <template #default="scope">
          {{ scope.row.level }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('返佣比例')">
        <template #default="scope">
          {{ $numInit(scope.row.rate * 100) }}%
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { v2_team_commissionRate_api } from "@/api/team";
import ApiStorage from "@/storage/ApiStorage";
import { V2TeamCommissionRateItem } from "@/types/api/team";
import { Ref, onActivated, ref } from "vue";


const loading = ref(false);
const list: Ref<V2TeamCommissionRateItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  await new ApiStorage({
    api: () => v2_team_commissionRate_api(),
    success: data => {
      list.value = data;
    }
  }).getData();
  loading.value = false;
};


onActivated(() => {
  getList();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/agent/commission.less"></style>