<template>
  <div class="card-box">
    <div class="top-input-box">
      <el-date-picker v-model="form.time" :start-placeholder="$t('开始日期')" :end-placeholder="$t('结束日期')" size="large"
        type="daterange" prefix-icon="none" class="round search" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
      <el-input class="round search" size="large" :placeholder="$t('搜索账户')" :suffix-icon="Search"
        v-model="form.username"></el-input>
    </div>
    <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>

    <el-table :data="list" size="large" class="t-table" v-else>
      <el-table-column :label="$t('邀请码')">
        <template #default="scope">
          {{ scope.row.rec_code }}
          <t-svg name="copy" class="copy-icon" @click="$copy(scope.row.rec_code)"></t-svg>
        </template>
      </el-table-column>
      <el-table-column :label="$t('注册时间')">
        <template #default="scope">
          {{ scope.row.add_time }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('总流⽔')">
        <template #default="scope">
          {{ $numInit(scope.row.total_bet) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('总充值')">
        <template #default="scope">
          {{ $numInit(scope.row.total_recharge) }}
        </template>
      </el-table-column>
      <template #append v-if="form.page !== -1">
        <More @loadmore="loadmore" :loading="loading"></More>
      </template>
    </el-table>
    <div class="foot-box">
      <div class="foot-item">
        <span>{{ $t("直属充值总额") }}</span>
        <span>{{ $numInit(statistics?.child_recharge) }}</span>
      </div>
      <div class="foot-item">
        <span>{{ $t("直属⾸充⼈数") }}</span>
        <span>{{ statistics?.child_user_num }}</span>
      </div>
      <div class="foot-item">
        <span>{{ $t("其他累积总充值") }}</span>
        <span>{{ $numInit(statistics?.other_recharge) }}</span>
      </div>
      <div class="foot-item">
        <span>{{ $t("其他⾸充⼈数") }}</span>
        <span>{{ statistics?.other_first_user_num }}</span>
      </div>
      <div class="foot-item">
        <span>{{ $t("总⾸充⾦额") }}</span>
        <span>{{ $numInit(statistics?.total_recharge) }}</span>
      </div>
      <div class="foot-item">
        <span>{{ $t("总⾸充⼈数") }}</span>
        <span>{{ statistics?.total_first_user_num }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { v2_team_statistics_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { V2TeamStatistics, V2TeamStatisticsItem } from "@/types/api/team";
import { Ref, onActivated, ref, watch } from "vue";
import More from "@/components/common/More.vue";
import { Search } from "@element-plus/icons-vue";
import antiShake from "@/utils/antiShake";
// 时间控制
const form = ref({
  page: 1,
  limit: 10,
  time: "",
  username: ""
});
watch(() => form.value.time, () => {
  form.value.page = 1;
  list.value = [];
  getList();
});
watch(() => form.value.username, antiShake(() => {
  form.value.page = 1;
  list.value = [];
  getList();
}));

const loading = ref(false);
const list: Ref<V2TeamStatisticsItem[]> = ref([]);
const statistics: Ref<V2TeamStatistics["statistics"] | null> = ref(null);
const getList = async () => {
  loading.value = true;
  const res = await v2_team_statistics_api({
    page: form.value.page,
    limit: form.value.limit,
    start_time: form.value.time?.[0],
    end_time: form.value.time?.[1],
    username: form.value.username
  });
  loading.value = false;
  if (res.code === ResCode.success) {
    list.value = [
      ...list.value,
      ...res.data,
    ];
    statistics.value = res.statistics;
    if (res.data.length < form.value.limit) form.value.page = -1;
  }
};

const loadmore = () => {
  form.value.page++;
  getList();
};
onActivated(() => {
  form.value.page = 1;
  list.value = [];
  getList();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/agent/allData.less"></style>