<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('轮廓')" :isBack="false"></NavBar>

    <div class="card-box user-box">
      <div class="user-warp">
        <t-img :src="userStore.userInfo?.head_img" class="avatar" />
        <div class="nickname-warp">
          <div>{{ $t("账号") }}: {{ userStore.userInfo?.yphone || userStore.userInfo?.mail }}</div>
          <div class="copy-warp">
            <span>ID: {{ userStore.userInfo?.rec_code }}</span>
            <t-svg name="copy" class="copy-icon" @click="$copy(userStore.userInfo?.rec_code)"></t-svg>
          </div>
        </div>
        <div class="balance-warp">
          <img :src="$require('10060.png')" class="balance-icon">
          <span class="balance">{{ $numInit(userStore.userInfo?.balance) }}</span>
          <Refresh :is-refresh="isRefresh" class="refresh-icon" @click="refreshBalance"></Refresh>
        </div>
      </div>
      <div class="user-nav-warp">
        <div class="user-nav-itme" @click="$router.push('/withdraw')">
          <t-svg name="Saque" class="user-nav-icon"></t-svg>
          <div class="user-nav-name">{{ $t("提现") }}</div>
        </div>
        <div class="user-nav-itme" @click="$openLink('/deposit')">
          <t-svg name="Depositar" class="user-nav-icon"></t-svg>
          <div class="user-nav-name">{{ $t("充值") }}</div>
        </div>
        <!-- <div class="user-nav-itme">
          <t-svg name="Juros" class="user-nav-icon"></t-svg>
          <div class="user-nav-name">{{ $t("费用") }}</div>
        </div> -->
      </div>
      <div class="user-vip-card">
        <div class="vip-title-warp">
          <div class="vip-label">{{ userStore.userInfo?.level_name }}</div>
          <div>
            {{ $t("满足条件升级") }}
            {{ userStore.userInfo?.next_level_name }}
          </div>
        </div>
        <div class="vip-progress-box">
          <t-img :src="userStore.userInfo?.level_image" class="vip-icon" />
          <div class="progress-warp">
            <div class="progress-item">
              <div class="progress-name">
                <span>{{ $t("充值金额") }}</span>
                <div class="progress-desc">{{ $t("您仍需 {data} 才能升级", {
                  data:
                    $numInit(Math.max(0, Number(userStore.userInfo?.next_level_recharge) -
                      Number(userStore.userInfo?.recharge1)))
                })
                  }}</div>
              </div>
              <el-progress :text-inside="true"
                :percentage="(Number(userStore.userInfo?.recharge1) / Number(userStore.userInfo?.next_level_recharge) || 0) * 100"
                class="progress t-progress">
                <span>{{ Number(userStore.userInfo?.recharge1) || 0 }}/{{
                  Number(userStore.userInfo?.next_level_recharge) || 0 }}</span>
              </el-progress>

            </div>
            <div class="progress-item">
              <div class="progress-name">
                <span>{{ $t("投注金额") }}</span>
                <div class="progress-desc">{{ $t("您仍需 {data} 才能升级", {
                  data:
                    $numInit(Math.max(0, Number(userStore.userInfo?.next_level_water) - Number(userStore.userInfo?.water))
                    )
                }) }}</div>
              </div>
              <el-progress :text-inside="true"
                :percentage="(Number(userStore.userInfo?.water) / Number(userStore.userInfo?.next_level_water) || 0) * 100"
                class="progress t-progress">
                <span>{{ Number(userStore.userInfo?.water) || 0 }}/{{ Number(userStore.userInfo?.next_level_water || 0)
                  }}</span>
              </el-progress>
            </div>

          </div>

        </div>
      </div>
    </div>
    <div class="card-box nav-box">
      <!-- <div class="nav-item" @click="$router.push('/retrieveTheBalance')">
        <t-svg name="quhuiyue" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("取回余额") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div> -->
      <!-- <div class="nav-item" @click="$router.push('/report?current=0')">
        <t-svg name="statement" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("账户详细资料") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div> -->
      <!-- <div class="nav-item" @click="$router.push('/report?current=1')">
        <t-svg name="betRecords" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("投注记录") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div> -->
      <!-- <div class="nav-item" @click="$router.push('/report?current=2')">
        <t-svg name="reorts" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("报告") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div> -->
      <div class="nav-item" @click="$router.push('/withdraw?current=2')">
        <t-svg name="withdrawalManage" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("提款管理") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
    </div>
    <div class="card-box nav-box">

      <div class="nav-item" @click="$router.push('/agent/shareLink')">
        <t-svg name="retrieveTheBalance" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("邀请") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
      <div class="nav-item" @click="$router.push('/securityCenter')">
        <t-svg name="securityCenter" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("安全中心") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
      <div class="nav-item" @click="$openLink('/music')">
        <t-svg name="music" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("音乐") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
      <div class="nav-item" @click="$router.push('/msgCenter?current=0')">
        <t-svg name="kefu" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("支持") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>

      <div class="nav-item" @click="$router.push('/msgCenter?current=2')">
        <t-svg name="liuyan" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("留言") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>

      <div class="nav-item" @click="$router.push('/profile')">
        <t-svg name="bianji" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("轮廓") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>

      <div class="nav-item" @click="$router.push('/msgCenter?current=0')">
        <t-svg name="question" class="nav-icon" style="transform: scale(.8);"></t-svg>
        <div class="nav-item-name">{{ $t("常问问题") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>


      <div class="nav-item" @click="$router.push('/msgCenter?current=4')">
        <t-svg name="feedback" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("反馈") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>

      <div class="nav-item" @click="quit">
        <t-svg name="quit" class="nav-icon"></t-svg>
        <div class="nav-item-name">{{ $t("出去") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import useStore from "@/store";
import quit from "@/utils/quit";
import NavBar from "@/components/common/NavBar.vue";
import { ref } from "vue";
const { userStore } = useStore();

// 刷新余额
const isRefresh = ref(false);
const refreshBalance = async () => {
  if (isRefresh.value) return;
  isRefresh.value = true;
  await userStore.getUserInfo();
  isRefresh.value = false;
};
</script>

<style scoped lang="less" src="@/assets/css/pages/userCenter.less"></style>