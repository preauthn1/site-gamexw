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
      <el-table-column :label="$t('是否充值')">
        <template #default="scope">
          {{ $t(scope.row.is_recharge === 1 ? "是" : "否") }}
        </template>
      </el-table-column>
      <el-table-column :label="$t('状态')">
        <template #default="scope">
          {{ $t(scope.row.status === 1 ? "正常" : "冻结") }}
        </template>
      </el-table-column>
      <template #append v-if="form.page !== -1">
        <More @loadmore="loadmore" :loading="loading"></More>
      </template>
    </el-table>
  </div>
</template>

<script setup lang="ts">
import { v2_team_childMember_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { V2TeamChildMemberItem } from "@/types/api/team";
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
const list: Ref<V2TeamChildMemberItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  const res = await v2_team_childMember_api({
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