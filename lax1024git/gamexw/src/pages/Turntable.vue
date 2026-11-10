<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('总投注幸运旋转')">
      <template #right>
        <el-button type="primary" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
      </template>
    </NavBar>
    <div class="content-box">
      <div class="tip">
        <span>{{ $t("当前余额") }}</span>
        <span class="tip-num">{{ $numInit(urnrableTabsActiveData?.balance) }}</span> &nbsp;&nbsp;
        <span>{{ $t("当前积分") }}</span>
        <span class="tip-num">{{ $numInit(urnrableTabsActiveData?.integral) }}</span>
      </div>
      <div class="content-warp">

        <!-- 转盘 -->
        <div class="content-turnrable">
          <div class="turnrable-tabs">
            <div :class="['turnrable-tabs-item', urnrableTabsActive === index ? 'active' : '', item.theme_type]"
              @click="urnrableTabsActive = index" v-for="item, index in turntableList" :key="item.id">
              <div class="turnrable-tabs-item-name">{{ item.title }}</div>
              <div class="turnrable-tabs-item-tip" v-if="item.free_count === -1"> {{ $t("免费") }}</div>
              <div class="turnrable-tabs-item-tip" v-else-if="item.free_count > 0">
                {{ $t("免费{num}次", { num: item.free_count }) }}
              </div>
              <div class="turnrable-tabs-item-tip" v-else>
                <span v-if="item.required_integral > 0">{{ item.required_integral }}{{ $t("积分") }}</span>
                <span v-if="Number(item.required_balance) > 0 && item.required_integral > 0">/</span>
                <span v-if="Number(item.required_balance) > 0">{{ Number(item.required_balance) }}{{ $t("余额") }}</span>
              </div>
            </div>
          </div>
          <div class="turnrable-warp">
            <Turnrable class="turnrable" :type="urnrableTabsActiveData.theme_type" :data="urnrableTabsActiveData"
              v-if="urnrableTabsActiveData" @start="start" @end="end" ref="turnrableRef">
            </Turnrable>
          </div>
        </div>

        <!-- 记录 -->
        <div class="content-records">
          <Tabs v-model:active="active" class="tabs" animated>
            <Tab :title="$t(tabs.title)" v-for="tabs in recordsListTabs" :key="tabs.title">
              <Empty class="empty" v-if="tabs.list.length === 0"></Empty>
              <div class="list" v-else>
                <div class="records-item" v-for="item in tabs.list" :key="item.id">
                  <img :src="item.pic" class="records-item-icon">
                  <div class="records-item-time-warp">
                    <div>{{ item.time }}</div>
                    <div>{{ item.username }}</div>
                  </div>
                  <div class="records-item-amount" v-if="item.prize_type === 1">{{ $numInit(item.prize_price) }}</div>
                  <div class="records-item-amount" v-if="item.prize_type === 2">{{ item.title }}</div>
                  <div class="records-item-amount" v-if="item.prize_type === 3">{{ $t("谢谢参与") }}</div>
                </div>
                <More @loadmore="lotteryrecordMore(tabs)" :loading="tabs.loading" v-if="tabs.params.page !== -1"></More>
              </div>
            </Tab>
          </Tabs>
        </div>
      </div>
    </div>
    <div class="rule-box">
      <div class="rule-title">{{ $t("活动详情") }}</div>
      <div class="rule-content">{{ $t("总投注幸运旋转详情介绍") }}</div>
    </div>
    <AllActivity></AllActivity>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import AllActivity from "@/components/common/AllActivity.vue";
import Turnrable from "@/components/common/Turnrable.vue";
import { Ref, computed, onActivated, ref } from "vue";
import { Tab, Tabs } from "vant";
import { action_lottery_api, action_lotteryconfiglist_api, action_lotteryrecord_api } from "@/api/action";
import { ResCode } from "@/enum/ResultCode";
import { Lotteryconfig, LotteryrecordItem } from "@/types/api/action";
import { ElMessage } from "element-plus";
import { ElementType } from "@/types/utils";
import lang from "@/lang";
import useStore from "@/store";
import Loading from "@/utils/loading";
import Empty from "@/components/common/Empty.vue";
const { userStore } = useStore();
const turnrableRef: Ref<InstanceType<typeof Turnrable> | null> = ref(null);
const urnrableTabsActive = ref(0);


// 选中的转盘数据
const urnrableTabsActiveData = computed(() => {
  if (turntableList.value.length === 0) {
    return null;
  }
  return turntableList.value[urnrableTabsActive.value];
});

// 获取数据
const turntableList = ref([] as Lotteryconfig[]);
const getData = async () => {
  const res = await action_lotteryconfiglist_api();
  if (res.code === ResCode.success) {
    turntableList.value = res.data;
  }
};

// 获取抽奖记录
const recordsListTabs = ref([
  {
    title: "获奖通知",
    loading: false,
    params: {
      prize_type: 1,
      page: 1,
      limit: 20,
      state: 0
    },
    list: [] as LotteryrecordItem[]
  },
  {
    title: "我的记录",
    loading: false,
    params: {
      prize_type: 0,
      page: 1,
      limit: 20,
      state: 1
    },
    list: [] as LotteryrecordItem[]

  }
]);
const active = ref(0);

// 获取记录
const getLotteryrecord = async (recordsListItem: ElementType<typeof recordsListTabs.value>) => {
  const res = await action_lotteryrecord_api({
    id: -1,
    ...recordsListItem.params
  });
  if (res.code === ResCode.success) {
    if (recordsListItem.params.page === 1) {
      recordsListItem.list = res.data.data;
    } else {
      recordsListItem.list = [...recordsListItem.list, ...res.data.data];
    }
    if (res.data.data.length < recordsListItem.params.limit) recordsListItem.params.page = -1;
  }
};
const lotteryrecordMore = async (recordsListItem: ElementType<typeof recordsListTabs.value>) => {
  recordsListItem.params.page++;
  recordsListItem.loading = true;
  await getLotteryrecord(recordsListItem);
  recordsListItem.loading = false;
};


// 旋转之后返回的数据
const turntableStartData: Ref<ElementType<Lotteryconfig["Prize"]> | null> = ref(null);

// 开始旋转
const start = async () => {
  if (!turnrableRef.value) return;
  turnrableRef.value.loading = true;
  const res = await action_lottery_api({ id: urnrableTabsActiveData.value?.id });
  turnrableRef.value.loading = false;
  if (res.code === ResCode.success) {
    turnrableRef.value.start(res.data.id);
    turntableStartData.value = res.data;
    initData();
    userStore.getUserInfo();
  }
};
// 旋转结束
const end = () => {
  let text = "";
  if (turntableStartData.value?.type === 1) {
    text = lang.t("恭喜您获得{num}红包奖励", { num: turntableStartData.value.amount });
  }
  if (turntableStartData.value?.type === 2) {
    text = lang.t("恭喜您获得{title}奖励", { title: turntableStartData.value.title });
  }
  if (turntableStartData.value?.type === 3) {
    text = lang.t("谢谢参与");
  }
  ElMessage({
    type: "success",
    message: text
  });
};

const initData = async () => {
  recordsListTabs.value[0].params.page = 1;
  recordsListTabs.value[1].params.page = 1;
  await Promise.all([
    getData(),
    getLotteryrecord(recordsListTabs.value[0]),
    getLotteryrecord(recordsListTabs.value[1])
  ]);
};

onActivated(async () => {
  Loading.show();
  await initData();
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/turntable.less"></style>