<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('神秘奖金')">
      <template #right>
        <div>
          <el-button type="primary" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
          <el-button type="primary" @click="signIn" class="m-hide" :loading="btnLoading"
            v-if="currentDay?.status === SignStatus.notCheckedIn">{{ $t("签到") }}</el-button>
          <el-button type="primary" disabled v-else class="m-hide">{{ $t("已签到") }}</el-button>
        </div>
      </template>
    </NavBar>
    <div class="sing-box">
      <div class="data-list">
        <div class="data-item">
          <span>{{ $t("连续签到{day}天", { day: signData?.sign_day || 0 }) }}</span>
        </div>
        <div class="data-item">
          <span>{{ $t("已收到") }}</span>
          <span style="color: var(--color6);">{{ $numInit(signData?.total_amount) }}</span>
        </div>
        <div class="data-item" v-if="Number(signData?.min_recharge) > 0">
          <span>{{ $t("充值") }}</span>
          <span style="color: var(--color7);">
            {{ $numInit(signData?.current_recharge) }}/{{ $numInit(signData?.min_recharge) }}
          </span>
        </div>
        <div class="data-item" v-if="Number(signData?.min_bet) > 0">
          <span>{{ $t("投注") }}</span>
          <span style="color: var(--color7);">
            {{ $numInit(signData?.current_recharge) }}/{{ $numInit(signData?.min_bet) }}
          </span>
        </div>
      </div>
      <div class="sing-warp">
        <div class="sing-item" :class="{ 'sing-item-active': item.day === signData?.current_day }"
          v-for="item, index in signData?.day_list" :key="index"
          @click="() => item.day === signData?.current_day && signIn()">
          <t-svg name="sign" class="sign-icon" v-if="item.status === SignStatus.signedIn"></t-svg>
          <div class="item-name">{{ $t("第{day}天", { day: item.day }) }}</div>
          <img :src="$require('jinbi.png')" class="item-icon" v-if="Number(item.amount) > 0">
          <img :src="$require('integral.png')" class="item-icon" style="transform: scale(1.1)" v-else>

          <div class="item-price">
            <span>{{ Number(item.amount) > 0 ? $numInit(item.amount) : item.integral }}</span>
          </div>
        </div>
      </div>
    </div>
    <div class="rule-box">
      <div class="rule-title">{{ $t("活动详情") }}</div>
      <div class="rule-content html" v-html="signData?.introduce"></div>
    </div>
    <AllActivity></AllActivity>
    <div class="foot pc-hide">
      <div class="foot-content">
        <el-button class="full" size="large" plain @click="$router.back">{{ $t("后退") }}</el-button>
        <el-button type="primary" class="full" size="large" @click="signIn" :loading="btnLoading"
          v-if="currentDay?.status === SignStatus.notCheckedIn">{{ $t("签到") }}</el-button>
        <el-button type="primary" class="full" size="large" disabled v-else>{{ $t("已签到") }}</el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import AllActivity from "@/components/common/AllActivity.vue";
import { sign_getSign_api, sign_userSign_api } from "@/api/sign";
import { ResCode } from "@/enum/ResultCode";
import { Ref, computed, onMounted, ref } from "vue";
import { SignData } from "@/types/api/sign";
import { SignStatus } from "@/enum/SignStatus";
import { ElMessage } from "element-plus";
import lang from "@/lang";
import useStore from "@/store";
import Loading from "@/utils/loading";

const { userStore } = useStore();


// 获取数据
const signData: Ref<SignData | null> = ref(null);
const getSignData = async () => {
  const res = await sign_getSign_api();
  if (res.code === ResCode.success) {
    signData.value = res.data;
  }
};

// 当前所在天数数据
const currentDay = computed(() => {
  if (!signData.value) return null;
  return signData.value.day_list.find(item => item.day === signData.value?.current_day);
});

// 签到
const btnLoading = ref(false);
const signIn = async () => {
  if (!signData.value) return;
  if (btnLoading.value) return;
  btnLoading.value = true;
  const res = await sign_userSign_api({
    sign_id: signData.value.id,
    day: signData.value.current_day
  });
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: lang.t("成功签到")
    });
    userStore.getUserInfo();
    await getSignData();
  }
  btnLoading.value = false;
};



onMounted(async () => {
  Loading.show();
  await getSignData();
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/signIn.less"></style>