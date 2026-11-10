<template>
  <div class="card-box">
    <div class="title-box">
      <div style="display:flex;align-items: center;cursor: pointer;" @click="emit('back')">
        <t-svg name="left" /> &nbsp; {{ $t("佣金详情") }}
      </div>
    </div>
    <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>
    <el-table :data="list" size="large" class="t-table" v-else>
      <el-table-column :label="$t('邀请码')">
        <template #default="scope">
          {{ scope.row.rec_code }}
          <t-svg name="copy" class="copy-icon" @click="$copy(scope.row.rec_code)"></t-svg>
        </template>
      </el-table-column>
      <el-table-column :label="$t('日期')">
        <template #default="scope">
          {{ scope.row.sffl_dt }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('流⽔')">
        <template #default="scope">
          {{ $numInit(scope.row.valid_bet) }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('佣⾦')">
        <template #default="scope">
          {{ $numInit(scope.row.commission) }}
        </template>
      </el-table-column>
      <template #append v-if="form.page !== -1">
        <More @loadmore="loadmore" :loading="loading"></More>
      </template>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { v2_team_commissionDetail_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { V2TeamCommissionDetailItem } from "@/types/api/team";
import { Ref, ref } from "vue";
import More from "@/components/common/More.vue";
import { onMounted } from "vue";
const emit = defineEmits(["back"]);

const props = defineProps<{
  id: number
}>();

// 时间控制
const form = ref({
  page: 1,
  limit: 10,
});


const loading = ref(false);
const list: Ref<V2TeamCommissionDetailItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  const res = await v2_team_commissionDetail_api({
    page: form.value.page,
    limit: form.value.limit,
    id: props.id
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
  form.value.page = 1;
  list.value = [];
  getList();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/agent/commission.less"></style>