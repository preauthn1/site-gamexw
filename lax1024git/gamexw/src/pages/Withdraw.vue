<template>
  <div class="page-box">
    <NavBar :title="$t('提现')" class="nav-bar m-hide"></NavBar>
    <div class="card-box">
      <Tabs v-model:active="active" :shrink="true" class="tabs" animated :sticky="systemStore.isPhone">
        <Tab :title="$t('提现')" replace to="?current=0">
          <div class="page-warp">
            <Withdraw></Withdraw>
          </div>
        </Tab>
        <Tab :title="$t('提现记录')" replace to="?current=1">
          <div class="page-warp">
            <WithdrawRecords></WithdrawRecords>
          </div>
        </Tab>
        <!-- <Tab :title="$t('审计记录')" to="?current=2" replace>
          <div class="page-warp">
            <AuditReport></AuditReport>
          </div>
        </Tab> -->
        <Tab :title="$t('管理账户')" to="?current=2" replace>
          <div class="page-warp">
            <ManageAccount></ManageAccount>
          </div>
        </Tab>
        <template #nav-left>
          <div class="nav-back pc-hide" @click="$router.back">
            <t-svg name="left"></t-svg>
          </div>
        </template>
      </Tabs>
    </div>

  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { ref, watch } from "vue";
import { Tab, Tabs } from "vant";
import { useRoute } from "vue-router";
import Withdraw from "@/components/page/withdraw/Withdraw.vue";
import WithdrawRecords from "@/components/page/withdraw/WithdrawRecords.vue";
// import AuditReport from "@/components/page/withdraw/AuditReport.vue";
import ManageAccount from "@/components/page/withdraw/ManageAccount.vue";
import useStore from "@/store";
const { systemStore } = useStore();
const $route = useRoute();
const active = ref(0);
watch(() => $route.query.current, v => {
  if ($route.path === "/withdraw") {
    active.value = Number(v) || 0;
  }
}, { immediate: true });
</script>

<style scoped lang="less" src="@/assets/css/pages/withdraw/index.less"></style>