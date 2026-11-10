<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('消息中心')"></NavBar>
    <Tabs v-model:active="active" :shrink="true" class="tabs" animated :before-change="beforeChange">
      <Tab :title="$t('支持')" to="?current=0" replace>
        <Support class="content-box"></Support>
      </Tab>
      <Tab :title="$t('消息')" :to="userStore.isLogin ? '?current=1' : undefined" replace>
        <Msg class="content-box"></Msg>
      </Tab>
      <Tab :title="$t('通知')" :to="userStore.isLogin ? '?current=2' : undefined" replace>
        <MsgNotice class="content-box"></MsgNotice>
      </Tab>
      <Tab :title="$t('滚动面板')" to="?current=3" replace>
        <ScrollPanel class="content-box"></ScrollPanel>
      </Tab>
      <Tab :title="$t('反馈')" to="?current=4" replace>
        <Proposal class="content-box"></Proposal>
      </Tab>
    </Tabs>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { Tab, Tabs } from "vant";
import { ref, watch } from "vue";
import { useRoute } from "vue-router";
import Support from "@/components/page/msgCenter/Support.vue";
import Msg from "@/components/page/msgCenter/Msg.vue";
import MsgNotice from "@/components/page/msgCenter/MsgNotice.vue";
import ScrollPanel from "@/components/page/msgCenter/ScrollPanel.vue";
import Proposal from "@/components/page/msgCenter/Proposal.vue";
import useStore from "@/store";
import { openLink } from "@/utils/openLink";
const { userStore } = useStore();
const $route = useRoute();
const active = ref(0);
watch(() => $route.query.current, v => {
  if ($route.path === "/msgCenter") {
    active.value = Number(v) || 0;
  }
}, { immediate: true });


const beforeChange = (v: number) => {
  // 登录验证
  if (!userStore.isLogin && (v === 1 || v === 2)) {
    openLink("/login");
    return;
  }
  return true;
};
</script>

<style scoped lang="less" src="@/assets/css/pages/msgCenter/msgCenter.less"></style>