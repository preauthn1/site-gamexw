<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('报告')"></NavBar>
    <div class="card-box">
      <Tabs v-model:active="active" :shrink="true" class="tabs" animated>
        <Tab :title="$t('投注记录')" to="?current=0" replace>
          <div class="input-list">
            <el-select class="round input" size="large" v-model="form.level" :placeholder="$t('级别')">
              <el-option :label="$t(item.name)" :value="item.value" v-for="item in levelList" :key="item.value" />
            </el-select>

            <el-select class="round input" size="large" v-model="form.settlement" :placeholder="$t('结算')">
              <el-option :label="$t(item.name)" :value="item.value" v-for="item in settlementList" :key="item.value" />
            </el-select>
            <div>
              <el-date-picker v-model="form.time" :start-placeholder="$t('开始日期')" :end-placeholder="$t('结束日期')"
                size="large" type="daterange" prefix-icon="none" class="round " format="YYYY-MM-DD"
                value-format="YYYY-MM-DD" />
            </div>
          </div>
          <Empty class="empty" :loading="loading" v-if="list.length === 0"></Empty>
          <el-table :data="list" size="large" class="t-table" v-else>
            <el-table-column :label="$t('用户名')">
              <template #default="scope">
                {{ scope.row.username }}
              </template>
            </el-table-column>
            <el-table-column :label="$t('输赢金额')">
              <template #default="scope">
                {{ $numInit(scope.row.sy_res) }}
              </template>
            </el-table-column>
            <el-table-column :label="$t('状态')">
              <template #default="scope">
                {{ $t(statusData[scope.row.status]) }}
              </template>
            </el-table-column>
            <el-table-column :label="$t('下注时间')">
              <template #default="scope">
                {{ scope.row.add_time }}
              </template>
            </el-table-column>
            <el-table-column :label="$t('单号')" :width="systemStore.isPhone ? 150 : 200">
              <template #default="scope">
                <span class="order-num">{{ scope.row.zd_number }}</span>
                <t-svg name="copy" class="copy-icon" @click="$copy(scope.row.zd_number)"></t-svg>
              </template>
            </el-table-column>
            <template #append v-if="form.page !== -1">
              <More @loadmore="loadmore" :loading="loading"></More>
            </template>
          </el-table>
        </Tab>
      </Tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { Ref, onActivated, ref, watch } from "vue";
import { Tab, Tabs } from "vant";
import { useRoute } from "vue-router";
import { ResCode } from "@/enum/ResultCode";
import { team_orderlist_api } from "@/api/team";
import { TeamOrderlistItem } from "@/types/api/team";
import { levelList } from "@/data/levelList";
import { settlementList } from "@/data/settlement";
import More from "@/components/common/More.vue";
import Empty from "@/components/common/Empty.vue";
import useStore from "@/store";
const { systemStore } = useStore();
const $route = useRoute();
const active = ref(0);
watch(() => $route.query.current, v => {
  if ($route.path === "/report") {
    active.value = Number(v) || 0;
  }
}, { immediate: true });



const statusData = {
  1: "已下单",
  2: "已注销",
  3: "输",
  4: "赢",
};






// 时间控制
const form = ref({
  page: 1,
  limit: 10,
  time: "",
  settlement: "",
  level: "",
});

watch(() => [form.value.time, form.value.level, form.value.settlement], () => {
  form.value.page = 1;
  list.value = [];
  getList();
});

const loading = ref(false);
const list: Ref<TeamOrderlistItem[]> = ref([]);
const getList = async () => {
  loading.value = true;
  const res = await team_orderlist_api({
    page: form.value.page,
    limit: form.value.limit,
    settlement: form.value.settlement,
    level: form.value.level,
    date: form.value.time && form.value.time?.[0] + "|" + form.value.time?.[1],
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

<style scoped lang="less" src="@/assets/css/pages/report.less"></style>