<template>
  <div class="page-box">
    <NavBar :title="$t('安全中心')" class="nav-bar"></NavBar>
    <div class="cell-list">
      <div class="cell-item">
        <t-svg name="user" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("用户名") }}</span>
        <div class="cell-content">{{ userStore.userInfo?.yphone || userStore.userInfo?.mail }}</div>
      </div>
      <div class="cell-item" @click="(!userStore.userInfo?.yphone) && $router.push('/phone')">
        <t-svg name="shouji2" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("电话号码") }}</span>
        <div class="cell-content" v-if="!userStore.userInfo?.yphone">{{ $t("链接") }}</div>
        <div class="cell-content" v-else>{{ `+${userStore.userInfo?.qh} ${userStore.userInfo?.yphone}` }}</div>
        <t-svg name="right" class="right-icon" v-if="!userStore.userInfo?.yphone"></t-svg>
      </div>
      <div class="cell-item" @click=" (!userStore.userInfo?.mail) && $router.push('/email')">
        <t-svg name="youxiang" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("电子邮件地址") }}</span>
        <div class="cell-content">{{ userStore.userInfo?.mail || $t("链接") }}</div>
        <t-svg name="right" class="right-icon" v-if="!userStore.userInfo?.mail"></t-svg>
      </div>
      <div class="cell-item"
        @click="userStore.userInfo?.goodsle_bind === OpenState.close && $router.push('/googleAuthenticator')"
        v-if="systemStore.systemData?.data.user_google_status === OpenState.open">
        <t-svg name="g" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("谷歌身份验证器") }}</span>
        <div class="cell-content">{{ userStore.userInfo?.goodsle_bind === OpenState.open ? $t("已验证") : $t("链接") }}</div>
        <t-svg name="right" class="right-icon" v-if="userStore.userInfo?.goodsle_bind === OpenState.close"></t-svg>
      </div>
    </div>

    <div class="cell-list">
      <div class="cell-item" @click="$router.push('/loginPass')">
        <t-svg name="key" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("登录密码") }}</span>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
      <div class="cell-item" @click="$router.push('/withdrawalPass')"
        v-if="userStore.userInfo?.pay_passwd_verify === OpenState.open">
        <t-svg name="qianbao" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("提现密码") }}</span>
        <div class="cell-content" v-if="!userStore.userInfo?.withdrawal_psd">{{ $t("未配置") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
      <!-- <div class="cell-item" @click="$router.push('/securityQuestion')">
        <t-svg name="anquan" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("安全问题") }}</span>
        <div class="cell-content">{{ $t("未配置") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div> -->
    </div>

    <!-- <div class="cell-list">
      <div class="cell-item" @click="$router.push('/linkThirdLogin')">
        <t-svg name="link" class="cell-icon"></t-svg>
        <span class="cell-name">{{ $t("链接第三方登录") }}</span>
        <div class="cell-content">{{ $t("未配置") }}</div>
        <t-svg name="right" class="right-icon"></t-svg>
      </div>
    </div> -->
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { OpenState } from "@/enum/OpenState";
import useStore from "@/store";
const { userStore, systemStore } = useStore();
</script>

<style scoped lang="less" src="@/assets/css/pages/securityCenter.less"></style>