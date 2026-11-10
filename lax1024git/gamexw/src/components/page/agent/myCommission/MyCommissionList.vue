<template>
  <div class="card-box">
    <div class="title-box">
      <span>{{ $t("我的佣金") }}</span>
      <div>
        <span>{{ $t("收到佣金") }}&nbsp;</span>
        <span style="color:var(--color4)">{{ $numInit(commiss) }}</span>
      </div>
    </div>
    <div class="top-input-box">
      <el-date-picker v-model="form.time" :start-placeholder="$t('开始日期')" :end-placeholder="$t('结束日期')" size="large"
        type="daterange" prefix-icon="none" class="round search" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
    </div>
    <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>
    <el-table :data="list" size="large" class="t-table" v-else>
      <el-table-column :label="$t('日期')">
        <template #default="scope">
          {{ scope.row.time }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('总流⽔')">
        <template #default="scope">
          {{ $numInit(scope.row.valid_bet) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('游戏⼈数')">
        <template #default="scope">
          {{ scope.row.user_num }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('获得佣⾦')">
        <template #default="scope">
          {{ $numInit(scope.row.commission) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('详情')">
        <template #default="scope">
          <div class="detail" @click="showInfo(scope.row)">
            <span>{{ $t("详情") }}</span>
            <t-svg name="detail" class="detail-icon"></t-svg>
          </div>

        </template>
      </el-table-column>
      <template #append v-if="form.page !== -1">
        <More @loadmore="loadmore" :loading="loading"></More>
      </template>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { v2_team_commission_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { V2TeamCommissionItem } from "@/types/api/team";
import { Ref, onActivated, ref, watch } from "vue";
import More from "@/components/common/More.vue";

const showInfo = (item: V2TeamCommissionItem) => {
  emit("showInfo", item);
};
const emit = defineEmits(["showInfo"]);
// 时间控制
const form = ref({
  page: 1,
  limit: 10,
  time: ""
});

watch(() => form.value.time, () => {
  form.value.page = 1;
  list.value = [];
  getList();
});

const loading = ref(false);
const list: Ref<V2TeamCommissionItem[]> = ref([]);
const commiss = ref(0);
const getList = async () => {
  loading.value = true;
  const res = await v2_team_commission_api({
    page: form.value.page,
    limit: form.value.limit,
    start_time: form.value.time?.[0],
    end_time: form.value.time?.[1]
  });
  loading.value = false;
  if (res.code === ResCode.success) {
    commiss.value = res.commission;
    list.value = [
      ...list.value,
      ...res.data
    ];
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

<style scoped lang="less" src="@/assets/css/pages/agent/commission.less"></style>