<template>
  <Popup v-model:show="isShow" @closed="emit('closed')" :position="systemStore.isPhone ? 'bottom' : 'center'">
    <div class="title">
      <t-svg name="left" class="left-icon pc-hide" @click="isShow = false"></t-svg>
      {{ $t("充值记录") }}
    </div>
    <div class="top-input-box">
      <el-date-picker v-model="form.time" :start-placeholder="$t('开始日期')" :end-placeholder="$t('结束日期')" size="large"
        type="daterange" prefix-icon="none" class="round search" format="YYYY-MM-DD" value-format="YYYY-MM-DD" />
    </div>
    <div class="content">
      <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>

      <el-table :data="list" size="large" class="t-table" v-else>
        <el-table-column :label="$t('类型')">
          <template #default="scope">
            {{ $t(typeData[scope.row.type]) }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('渠道')">
          <template #default="scope">
            {{ $t(channelData[scope.row.channel]) }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('申请时间')">
          <template #default="scope">
            {{ scope.row.add_time }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('申请金额')">
          <template #default="scope">
            {{ $numInit(scope.row.apply_price) }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('到账金额')">
          <template #default="scope">
            {{ $numInit(scope.row.arrival_price) }}
          </template>
        </el-table-column>


        <el-table-column :label="$t('状态')">
          <template #default="scope">
            {{ $t(statusData[scope.row.status]) }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('备注')">
          <template #default="scope">
            {{ scope.row.remarks }}
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
  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref, watch } from "vue";
import Empty from "./Empty.vue";
import useStore from "@/store";
import { recharge_record_api } from "@/api/recharge";
import { ResCode } from "@/enum/ResultCode";
import { RechargeRecordItem } from "@/types/api/recharge";

const { systemStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);

const typeData = {
  0: "线下",
  1: "线上",
};

const channelData = {
  0: "挂卡",
  1: "卡线上",
  3: "USDT挂码",
  2: "USDT线上",
  4: "电子钱包",
  6: "PIX钱包",
};

const statusData = {
  "-2": "已取消",
  "-1": "延迟未付款",
  1: "待审核",
  2: "未通过",
  3: "已通过",
  4: "已通过",
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
const list: Ref<RechargeRecordItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  const res = await recharge_record_api({
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
  isShow.value = true;
  getList();
});
</script>

<style scoped lang="less" src="@/assets/css/components/depositRecords.less"></style>