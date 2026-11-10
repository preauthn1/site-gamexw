<template>
  <div>
    <LinkCode class="link-code" :link="teamRecommendStore.data?.invite_url"></LinkCode>
    <div class="link-box">
      <div class="link-title">
        <div>{{ $t("我的佣金") }}</div>
        <div class="more-box" @click="$router.replace('/agent/myCommission')">
          <span>{{ $t("更多") }}</span>
          <t-svg name="right" class="right-icon"></t-svg>
        </div>
      </div>
      <div class="data-list">
        <div class="data-item">
          <div class="data-item-title">{{ $t("累计佣⾦") }}</div>
          <div class="num-color2">{{ $numInit(teamRecommendStore.data?.commission.total_commission) }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("已领取佣⾦") }}</div>
          <div class="num-color2">{{ $numInit(teamRecommendStore.data?.commission.received_commission) }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("最近⼀次佣⾦") }}</div>
          <div class="num-color2">{{ $numInit(teamRecommendStore.data?.commission.last_commission) }}</div>
        </div>
      </div>
    </div>
    <div class="link-box">
      <div class="link-title">
        <div>{{ $t("我的收入") }}</div>
        <div class="more-box" @click="$router.replace('/agent/myIncome')">
          <span>{{ $t("更多") }}</span>
          <t-svg name="right" class="right-icon"></t-svg>
        </div>
      </div>
      <div class="data-list">
        <div class="data-item">
          <div class="data-item-title">{{ $t("直属下级⽤户数") }}</div>
          <div class="num-color1">{{ teamRecommendStore.data?.income.child_user_num }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("团队投注总额") }}</div>
          <div class="num-color1">{{ $numInit(teamRecommendStore.data?.income.total_bet) }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("直属⽤户投注总额") }}</div>
          <div class="num-color1">{{ $numInit(teamRecommendStore.data?.income.child_bet) }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("其他⽤户的投注总额") }}</div>
          <div class="num-color1">{{ $numInit(teamRecommendStore.data?.income.other_bet) }}</div>
        </div>
      </div>
    </div>
    <div class="link-box">
      <div class="link-title">
        <div>{{ $t("直属⽤户投注统计") }}</div>
        <div class="more-box" @click="$router.replace('/agent/subordinateBetting')">
          <span>{{ $t("更多") }}</span>
          <t-svg name="right" class="right-icon"></t-svg>
        </div>
      </div>
      <div class="data-list">
        <div class="data-item">
          <div class="data-item-title">{{ $t("直属⽤户投注总额") }}</div>
          <div class="num-color1">{{ $numInit(teamRecommendStore.data?.child_statistics.child_bet) }}</div>
        </div>
        <div class="data-item">
          <div class="data-item-title">{{ $t("投注笔数") }}</div>
          <div class="num-color1">{{ teamRecommendStore.data?.child_statistics.child_bet_num }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import LinkCode from "@/components/common/LinkCode.vue";
import { onActivated } from "vue";
import Loading from "@/utils/loading";
import useStore from "@/store";
const { teamRecommendStore } = useStore();

onActivated(async () => {
  Loading.show();
  await teamRecommendStore.getData();
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/agent/shareLink.less"></style>