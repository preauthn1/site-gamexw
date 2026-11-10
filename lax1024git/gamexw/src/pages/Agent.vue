<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('代理')"></NavBar>
    <div class="user-box" v-if="$route.path == '/agent/shareLink'">
      <div class="collectable-warp">
        <div class="id-box">
          <div class="id-item">
            <div class="id-name">{{ $t('我的身份') }}</div>
            <div class="id">{{ userStore.userInfo?.rec_code }}</div>
            <t-svg name="copy" class="copy-icon" @click="$copy(userStore.userInfo?.rec_code)"></t-svg>
          </div>
          <div class="id-item">
            <div class="id-name">{{ $t('身份证上级') }}</div>
            <div class="id">{{ teamRecommendStore.data?.parent_code || $t("无") }}</div>
            <t-svg name="copy" class="copy-icon" v-if="teamRecommendStore.data?.parent_code"
              @click="$copy(teamRecommendStore.data?.parent_code)"></t-svg>
          </div>
        </div>
        <div class="collectable-box">
          <span>{{ $t("总收入") }}</span>
          <span class="collectable-num">{{ $numInit(teamRecommendStore.data?.total_commission) }}</span>
        </div>
      </div>
      <div class="btn-list">
        <el-button type="primary" class="btn" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
      </div>

    </div>
    <PcTabs :tabsList="pageList" v-if="!systemStore.isPhone">
      <router-view v-slot="{ Component }" v-if="$route.path.startsWith('/agent')">
        <keep-alive>
          <component :is="Component"></component>
        </keep-alive>
      </router-view>
    </PcTabs>
    <template v-else>
      <Tabs :shrink="true" class="tabs" :before-change="beforeChange" :active="active">
        <Tab :title="$t(item.name)" v-for="item in pageList" :key="item.name"></Tab>
      </Tabs>
      <router-view v-slot="{ Component }" v-if="$route.path.startsWith('/agent')">
        <keep-alive>
          <component :is="Component"></component>
        </keep-alive>
      </router-view>
    </template>

  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import PcTabs from "@/components/common/PcTabs.vue";
import useStore from "@/store";
import { Tab, Tabs } from "vant";
import { ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
const { systemStore, userStore, teamRecommendStore } = useStore();
const $route = useRoute();
const $router = useRouter();

const pageList = [
  { name: "推广教程", url: "/agent/agentNetwork" },
  { name: "我的推荐", url: "/agent/shareLink" },
  { name: "我的佣金", url: "/agent/myCommission" },
  { name: "我的收入", url: "/agent/myIncome" },
  { name: "所有数据", url: "/agent/allData" },
  { name: "直属投注", url: "/agent/subordinateBetting" },
  { name: "直属会员", url: "/agent/directMembers" },
  { name: "直属奖金", url: "/agent/directBonus" },
  { name: "返佣比例", url: "/agent/rebateRatio" },
  { name: "注册下属", url: "/agent/registerSubordinate" },
];
const active = ref(0);
watch(() => $route.path, (v) => {
  active.value = pageList.findIndex(item => item.url === v);
}, { immediate: true });
const beforeChange = (i: number) => {
  $router.replace(pageList[i].url || "");
  return true;
};

</script>

<style scoped lang="less" src="@/assets/css/pages/agent/agent.less"></style>