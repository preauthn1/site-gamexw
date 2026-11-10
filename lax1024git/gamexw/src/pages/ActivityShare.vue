<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('邀请即可获得奖金')">
      <template #right>
        <el-button type="primary" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
      </template>
    </NavBar>
    <LinkCode :link="teamData?.invite_url">
      <div class="link-bottom">{{ $t("有效下层人员") }} <span class="link">{{ remuneration?.total_num }}</span> {{ $t("人") }}
        <span class="link" @click="$openLink('/teamUser')">{{
          $t("细节") }}</span>
      </div>
    </LinkCode>
    <div class="statistics-card">
      <div class="statistics-title">{{ $t("实际晋级的成员有多少？（满足一下所有条件）") }}</div>
      <div class="statistics-item" v-if="Number(systemStore.systemData?.data.tg_condition_valid_bet) > 0">
        <span>{{ $t("下属有效流水") }}</span>
        <span>{{ $numInit(systemStore.systemData?.data.tg_condition_valid_bet) }}</span>
      </div>
      <div class="statistics-item" v-if="Number(systemStore.systemData?.data.tg_condition) > 0">
        <span>{{ $t("下属累计充值") }}</span>
        <span>{{ $numInit(systemStore.systemData?.data.tg_condition) }}</span>
      </div>
    </div>
    <div class="box-card">

      <div v-for="listItem, i in initRewardList(remuneration?.list || [])" :key="i" class="box-prent">
        <div class="box-list">
          <template v-for="item, index in listItem" :key="index">
            <div class="box-item" :class="{
              'box-item-finish': item.is_finish === RemunerationFinishType.ok,
              'box-item-open': item.is_receive === RemunerationReceiveType.received,
            }">
              <div class="box-img" v-loading="openLoadings[index]" @click="open(item, index)">
                <div class="box-price-open">{{ $numInit(item.stratum_bonus) }}</div>
                <div class="people-warp">
                  <span v-if="item.is_receive === RemunerationReceiveType.review">{{ $t("审核中") }}</span>
                  <span
                    v-if="item.is_receive === RemunerationReceiveType.notClaimed && item.is_finish === RemunerationFinishType.ok">{{
                      $t("领取") }}</span>
                  <span v-else>{{ item.stratum_number }} {{ $t("人") }}</span>
                </div>
              </div>
              <div class="box-price">{{ $numInit(item.stratum_bonus) }}</div>
            </div>
            <div class="icon-box">
              <t-svg name="right"></t-svg>
            </div>
          </template>
        </div>
        <div class="bottom-icon-box">
          <div class="bottom-icon">
            <t-svg name="right"></t-svg>
          </div>
        </div>
      </div>
    </div>
    <div class="rule-box" v-if="ruleData?.content">
      <div class="rule-title">{{ $t("活动详情") }}</div>
      <div class="rule-content html" v-html="ruleData?.content"></div>
    </div>
    <AllActivity></AllActivity>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import LinkCode from "@/components/common/LinkCode.vue";
import AllActivity from "@/components/common/AllActivity.vue";
import { team_getRemuneration_api, team_receiveInviteRebate_api, team_view_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { Ref, onMounted, ref } from "vue";
import { Remuneration, Team } from "@/types/api/team";
import { Rule } from "@/types/api";
import Loading from "@/utils/loading";
import useStore from "@/store";
import { RemunerationFinishType, RemunerationReceiveType } from "@/enum/Remuneration";
import { ElementType } from "@/types/utils";
import { ElMessage } from "element-plus";
import lang from "@/lang";
import { rule_index_api } from "@/api";
const { systemStore, userStore } = useStore();

const initRewardList = <T>(rewardList: T[]) => {
  const newArr: T[][] = [];
  const newList: T[] = JSON.parse(JSON.stringify(rewardList));
  while (newList.length) {
    newArr.push(newList.splice(0, 4));
  }
  return newArr;
};

// 获取列表数据
const remuneration: Ref<Remuneration | null> = ref(null);
const getRemuneration = async () => {
  const res = await team_getRemuneration_api();
  if (res.code === ResCode.success) {
    remuneration.value = res.data;
  }
};

// 领取
const openLoadings: Ref<boolean[]> = ref([]);
const open = async (item: ElementType<Remuneration["list"]>, index: number) => {
  if (!(item.is_receive === RemunerationReceiveType.notClaimed && item.is_finish === RemunerationFinishType.ok)) return;
  openLoadings.value[index] = true;
  const res = await team_receiveInviteRebate_api({ id: item.id });
  openLoadings.value[index] = false;
  if (res.code === ResCode.success) {
    item = remuneration.value?.list.find(data => data.id === item.id) || item;
    item.is_receive = RemunerationReceiveType.received;
    userStore.getUserInfo();
    ElMessage({
      type: "success",
      message: lang.t("领取成功")
    });
  }
};

const teamData: Ref<Team | null> = ref(null);
const getTeamView = async () => {
  const res = await team_view_api();
  if (res.code === ResCode.success) {
    teamData.value = res.data;
  }
};

// 获取规则
const ruleData: Ref<Rule | null> = ref(null);
const getRuleDate = async () => {
  const res = await rule_index_api({ type: 13 });
  if (res.code === ResCode.success) {
    ruleData.value = res.data;
  }
};

onMounted(async () => {
  Loading.show();
  await Promise.all([
    getRemuneration(),
    getTeamView(),
    getRuleDate()
  ]);
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/activityShare.less"></style>