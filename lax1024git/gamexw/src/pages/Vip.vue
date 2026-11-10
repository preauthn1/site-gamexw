<template>
  <div class="page-box">
    <NavBar title="VIP" class="nav-bar m-hide"></NavBar>
    <div class="cur-box">
      <div class="cur-label">{{ $t("当前等级") }}</div>
      <t-img :src="userStore.userInfo?.level_image" class="vip-icon" />
      <div class="cur-tip" v-if="nextLev">
        <span>{{ $t("要达到下个级别") }}</span>
        <span class="vip-lev">{{ userStore.userInfo?.next_level_name }}</span>
        <br class="pc-hide">
        <span>{{ $t("还需要下注") }}</span>
        <span class="price">{{ $numInit(Math.max(0, Number(nextLev.water) - Number(userStore.userInfo?.water)))
          }}</span>
        <span>{{ $t("还需要充值") }}</span>
        <span class="price">{{ $numInit(Math.max(0, Number(nextLev.recharge_amount) -
          Number(userStore.userInfo?.recharge1)))
          }}</span>
      </div>
      <div class="cur-tip" v-else>
        {{ vipList.length ? $t("恭喜您，已升到顶级") : "" }}
      </div>
      <div class="btn-list">
        <el-button type="primary" class="btn" @click="$router.push('/promotion/history')">{{ $t("历史") }}</el-button>
        <!-- 领取周奖金 -->
        <!-- 业务隐藏：周月奖金 -->
        <el-button type="primary" class="btn"
          v-if="curLev?.week_is_receive_vip_reward === 0 && curLev?.week_reward_amount > 0 && false" @click="receive(1)"
          :loading="btnLoadingW">{{
            $t("周奖金") }} {{ $numInit(curLev?.week_reward_amount) }}</el-button>

        <!-- 领取月奖金 -->
        <!-- 业务隐藏：周月奖金 -->
        <el-button type="primary" class="btn"
          v-if="curLev?.month_is_receive_vip_reward === 0 && curLev?.month_reward_amount > 0 && false"
          @click="receive(2)" :loading="btnLoadingM">{{
            $t("月奖金") }} {{
            $numInit(curLev?.month_reward_amount) }}</el-button>
      </div>
    </div>
    <div class="vip-level-list">
      <div class="vip-level-title">{{ $t("VIP等级列表") }}</div>
      <Tabs v-model:active="active" :shrink="true" class="tabs" animated>


        <Tab :title="$t('升级奖金')">
          <el-table :data="vipList" size="large" class="t-table">
            <el-table-column :label="$t('等级')">
              <template #default="scope">
                <img :src="scope.row.image" class="table-lev-icon">
              </template>
            </el-table-column>
            <el-table-column>
              <template #header>
                {{ $t("晋级流水") }}
                <el-tooltip :content="$t('晋级流水介绍')" placement="top">
                  <t-svg name="question" class="question-icon"></t-svg>
                </el-tooltip>
              </template>
              <template #default="scope">
                <div class="bet-item-box">
                  <span>{{ $numInit(scope.row.water) }}</span>
                  <el-progress :text-inside="true"
                    :percentage="(Number(userStore.userInfo?.water) / Number(vipList[scope.$index + 1]?.water)) * 100"
                    class="progress t-progress"
                    v-if="scope.row.id === userStore.userInfo?.level_id && vipList[scope.$index + 1]">
                    <span>{{ Number(userStore.userInfo?.water) }}/{{ Number(vipList[scope.$index + 1]?.water) }}</span>
                  </el-progress>
                </div>
              </template>
            </el-table-column>
            <el-table-column>
              <template #header>
                {{ $t("晋级充值") }}
                <el-tooltip :content="$t('晋级充值介绍')" placement="top">
                  <t-svg name="question" class="question-icon"></t-svg>
                </el-tooltip>
              </template>
              <template #default="scope">
                <div class="bet-item-box">
                  <span>{{ $numInit(scope.row.recharge_amount) }}</span>
                  <el-progress :text-inside="true"
                    :percentage="Number(userStore.userInfo?.recharge1) / Number(vipList[scope.$index + 1]?.recharge_amount) * 100"
                    class="progress t-progress"
                    v-if="scope.row.id === userStore.userInfo?.level_id && vipList[scope.$index + 1]">
                    <span>{{ Number(userStore.userInfo?.recharge1) }}/{{ Number(vipList[scope.$index +
                      1]?.recharge_amount) }}</span>
                  </el-progress>
                </div>
              </template>
            </el-table-column>

            <el-table-column :label="$t('奖金')">
              <template #default="scope">
                {{ scope.row.level_gold }}
              </template>
            </el-table-column>
          </el-table>
        </Tab>

        <!-- 业务隐藏：周月奖金 -->
        <Tab :title="$t('每周奖金')" v-if="false">
          <el-table :data="vipList" size="large" class="t-table">
            <el-table-column :label="$t('等级')">
              <template #default="scope">
                <img :src="scope.row.image" class="table-lev-icon">
              </template>
            </el-table-column>
            <el-table-column :label="$t('奖金流水限制')">
              <template #default="scope">
                {{ $numInit(scope.row.week_water) }}
              </template>
            </el-table-column>
            <el-table-column prop="bonus" :label="$t('奖金')">
              <template #default="scope">
                <span class="bouns">{{ $numInit(scope.row.week_reward_amount) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </Tab>

        <!-- 业务隐藏：周月奖金 -->
        <Tab :title="$t('每月奖金')" v-if="false">
          <el-table :data="vipList" size="large" class="t-table">
            <el-table-column :label="$t('等级')">
              <template #default="scope">
                <img :src="scope.row.image" class="table-lev-icon">
              </template>
            </el-table-column>
            <el-table-column :label="$t('奖金流水限制')">
              <template #default="scope">
                {{ $numInit(scope.row.month_water) }}
              </template>
            </el-table-column>
            <el-table-column prop="bonus" :label="$t('奖金')">
              <template #default="scope">
                <span class="bouns">{{ $numInit(scope.row.month_reward_amount) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </Tab>

        <Tab :title="$t('VIP特权')" v-if="false">
          <el-table :data="vipList" size="large" class="t-table">
            <el-table-column :label="$t('等级')">
              <template #default="scope">
                <img :src="scope.row.image" class="table-lev-icon">
              </template>
            </el-table-column>
            <el-table-column :label="$t('每天提现次数')" prop="day_limit"></el-table-column>
            <el-table-column :label="$t('每天免手续费提现次数')" prop="day_free_limit"></el-table-column>
          </el-table>
        </Tab>

      </Tabs>
    </div>
    <div class="rule-box">
      <div class="rule-title">{{ $t("VIP规则说明") }}</div>
      <div class="rule-content">
        {{ $t("VIP规则说明详情") }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tab, Tabs } from "vant";
import NavBar from "@/components/common/NavBar.vue";
import { Ref, computed, onActivated, ref } from "vue";
import { VipListItem } from "@/types/api/user";
import { user_receiveLevelReward_api, user_vip_api } from "@/api/user";
import { ResCode } from "@/enum/ResultCode";
import useStore from "@/store";
import Loading from "@/utils/loading";
import { ElMessage } from "element-plus";
import lang from "@/lang";
import ApiStorage from "@/storage/ApiStorage";
const active = ref(0);
const { userStore } = useStore();

const nextLev = computed(() => {
  const index = vipList.value.findIndex(item => item.id === userStore.userInfo?.level_id);
  return vipList.value[index + 1] || null;
});

const curLev = computed(() => {
  return vipList.value.find(item => item.id === userStore.userInfo?.level_id) || null;
});

// 获取数据
const vipList: Ref<VipListItem[]> = ref([]);
const getVipList = async () => {
  await new ApiStorage({
    api: () => user_vip_api(),
    success: data => {
      vipList.value = data;
    }
  }).getData();
};


// 领取奖金
const btnLoadingW = ref(false);
const btnLoadingM = ref(false);

const receive = async (reward_type: number) => {
  const loading = reward_type === 1 ? btnLoadingW : btnLoadingM;
  loading.value = true;
  const res = await user_receiveLevelReward_api({
    level_id: curLev.value?.id as number,
    reward_type
  });
  if (res.code === ResCode.success) {
    await getVipList();
    userStore.getUserInfo();
    ElMessage({
      type: "success",
      message: lang.t("领取成功")
    });
  }
  loading.value = false;
};


onActivated(async () => {
  Loading.show();
  await getVipList();
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/vip/vip.less"></style>