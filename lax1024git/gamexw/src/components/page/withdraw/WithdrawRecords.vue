<template>
  <div class="withdrawRecords-box">
    <div class="top-input-box">
      <el-date-picker v-model="form.time" :start-placeholder="$t('开始日期')" :end-placeholder="$t('结束日期')" size="large"
        type="daterange" prefix-icon="none" class="round search" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
    </div>
    <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>

    <el-table :data="list" size="large" class="t-table" v-else>
      <el-table-column :label="$t('渠道')">
        <template #default="scope">
          {{ $t(channelData[scope.row.channel]) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('申请金额')">
        <template #default="scope">
          {{ $numInit(scope.row.w_price) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('实际到账金额')">
        <template #default="scope">
          {{ $numInit(scope.row.arrival_price) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('手续费')">
        <template #default="scope">
          {{ $numInit(scope.row.w_service) }}
        </template>
      </el-table-column>

      <el-table-column :label="$t('状态')">
        <template #default="scope">
          {{ $t(statusData[scope.row.status]) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('申请时间')">
        <template #default="scope">
          {{ scope.row.add_time }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('备注')">
        <template #default="scope">
          {{ scope.row.w_remarks }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('订单号')" width="130">
        <template #default="scope">
          {{ scope.row.order_sn }}
        </template>
      </el-table-column>

      <template #append v-if="form.page !== -1">
        <More @loadmore="loadmore" :loading="loading"></More>
      </template>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { withdraw_record_api } from "@/api/withdraw";
import Empty from "@/components/common/Empty.vue";
import { ResCode } from "@/enum/ResultCode";
import { WithdrawRecordItem } from "@/types/api/withdraw";
import { Ref, onMounted, ref, watch } from "vue";

const channelData = {
  0: "银行卡",
  1: "USDT",
  2: "电子钱包",
  3: "PIX",
};

const statusData = {
  "-2": "已取消",
  "-1": "代付失败",
  1: "待审核",
  2: "未通过",
  3: "已通过",
  4: "已通过",
  5: "进行中",
  6: "封控审核"
};



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
const list: Ref<WithdrawRecordItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  const res = await withdraw_record_api({
    page: form.value.page,
    limit: form.value.limit,
    start_time: form.value.time?.[0] || "",
    end_time: form.value.time?.[1] || ""
  });
  loading.value = false;
  if (res.code === ResCode.success) {
    list.value = [
      ...list.value,
      ...res.data,
    ];
    if (res.data.length < form.value.limit) form.value.page = -1;
  }
};

const loadmore = () => {
  form.value.page++;
  getList();
};

onMounted(() => {
  getList();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/withdraw/withdrawRecords.less"></style>