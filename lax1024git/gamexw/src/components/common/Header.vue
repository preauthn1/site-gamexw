<template>
  <DownTips class="pc-hide"></DownTips>
  <div class="header">
    <div class="header-content" id="head">
      <div class="container">
        <div class="left-box">
          <TSvg name="menu" class="menu-icon" :class="{ 'menu-icon-hide': menuStore.isShow }"
            @click="menuStore.changeShow"></TSvg>
          <img
            :src="systemStore.isPhone ? systemStore.systemDataNew?.site.website_mobile_logo : systemStore.systemDataNew?.site.website_pc_logo"
            class="logo" @click="$router.push('/')" />
          <div class="time-box m-hide">
            {{ momentTimezone(currentTime).tz("America/Sao_Paulo").format("MM/DD HH:mm:ss") }} <br />
            (UTC—{{ moment.utc(currentTime).format('HH:mm') }})
          </div>
          <el-dropdown trigger="click">
            <el-button plain round class="btn-dropdown m-hide">
              <div class="btn-dropdown-content">
                <span>{{ lang.getLocale() }}</span>
                <TSvg name="caretBottom" class="caretBottom-icon"></TSvg>
              </div>
            </el-button>
            <template #dropdown>
              <el-dropdown-menu class="dropdown-menu">
                <el-dropdown-item v-for="item in systemStore.systemData?.alllang" :key="item.value"
                  @click="lang.setLocale(item.value, true)">{{ item.label
                  }}</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
          <div v-if="userStore.isLogin" class="balance-warp">
            <el-button plain round class="btn-balance">
              <div class="btn-balance-content">
                <img :src="$require('10060.png')" class="balance-icon" />
                <span class="balance">{{ $numInit(userStore.userInfo?.balance) }}</span>
                <Refresh :is-refresh="isRefresh" class="refresh-icon" @click="refreshBalance"></Refresh>
              </div>
            </el-button>

            <el-popover placement="bottom" trigger="contextmenu" v-model:visible="btnVisible" width="50">
              <div class="select">
                <div class="select-sub-item" @click="$router.push('/withdraw'); btnVisible = false">{{ $t("提现") }}</div>
                <!-- <div class="select-sub-item" @click="$router.push('/interest'); btnVisible = false">{{ $t("费用") }}</div> -->
              </div>
              <template #reference>
                <el-button type="primary" class="btn btn-select" @click.stop.prevent="$openLink('/deposit')">
                  {{ $t("充值") }}
                  <div class="btn-bottom-icon pc-hide" @click.stop="btnVisible = true">
                    <t-svg name="bottom2"></t-svg>
                  </div>
                </el-button>
              </template>
            </el-popover>
            <el-button plain class="btn m-hide" @click="$router.push('/withdraw')">{{ $t("提现") }}</el-button>
            <!-- <el-button plain class="btn m-hide" @click="$router.push('/interest')">{{ $t("费用") }}</el-button> -->
          </div>
        </div>
        <el-popover placement="bottom" trigger="click" v-if="userStore.isLogin && !systemStore.isPhone" width="auto"
          v-model:visible="menuVisible">
          <div class="select">
            <div class="select-item" v-for="item, i in menuList" :key="i">
              <div class="select-sub-item" v-for="subItem, i in item" :key="i" @click="tabMenu(subItem)">
                <t-svg class="select-item-icon" :name="subItem.icon"></t-svg>
                <span>{{ $t(subItem.name) }}</span>
              </div>
            </div>
          </div>
          <template #reference>
            <div class="right-box right-user">
              <div class="avatar-box">
                <t-img :src="userStore.userInfo?.head_img" class="avatar" />
                <div class="user-label">{{ userStore.userInfo?.level_name }}</div>
              </div>
              <div class="user-box">
                <div class="username">{{ userStore.userInfo?.yphone || userStore.userInfo?.mail }}</div>
                <div class="id">
                  <span>ID:{{ userStore.userInfo?.rec_code }}</span>
                  <TSvg name="copy" class="copy-icon" @click.stop="$copy(userStore.userInfo?.rec_code)"></TSvg>
                </div>
              </div>
              <TSvg name="caretBottom" class="user-caretBottom-icon"></TSvg>
            </div>
          </template>
        </el-popover>

        <div class="right-box" v-else-if="!userStore.isLogin">
          <el-button class="login-button" @click="$openLink('/login')">{{ $t("登录") }}</el-button>
          <el-button class="reg-button" @click="$openLink('/register')">{{ $t("注册") }}</el-button>
        </div>
        <t-svg name="search" class="pc-hide search-icon" @click="$router.push('/sub-games')"></t-svg>
      </div>

    </div>
  </div>
  <!-- 侧边栏 -->
  <Menu></Menu>
</template>

<script setup lang="ts">
import useStore from "@/store";
import DownTips from "@/components/page/index/DownTips.vue";
import Menu from "@/components/common/Menu.vue";
import { ref } from "vue";
import lang from "@/lang";
import { ElementType } from "@/types/utils";
import { openLink } from "@/utils/openLink";
import quit from "@/utils/quit";
import moment from "moment";
import momentTimezone from "moment-timezone";
import { currentTime } from "@/utils/currentTime";

const { menuStore, userStore, systemStore } = useStore();
const menuList = [
  [{ name: "代理", icon: "agent", url: "/agent" }],
  [
    // { name: "账户详细资料", icon: "statement", url: "/report?current=0" },
    { name: "投注记录", icon: "betRecords", url: "/report?current=0" },
    // { name: "报告", icon: "reorts", url: "/report?current=2" },
    { name: "消息中心", icon: "messageCenter", url: "/msgCenter" },
  ],
  [
    // { name: "取回余额", icon: "retrieveTheBalance", url: "/retrieveTheBalance" },
    { name: "提款管理", icon: "withdrawalManage", url: "/withdraw?current=2" },
    { name: "轮廓", icon: "profile", url: "/profile" },
    { name: "安全中心", icon: "securityCenter", url: "/securityCenter" },
  ],
  [
    { name: "音乐", icon: "music", url: "/music" },
    { name: "反馈", icon: "feedback", url: "/msgCenter?current=4" },
    { name: "常见问题", icon: "question2", url: "/msgCenter?current=0" },
    { name: "退出", icon: "quit", callBack: quit },
  ],
];
const menuVisible = ref(false);
const btnVisible = ref(false);
const tabMenu = (item: ElementType<ElementType<typeof menuList>>) => {
  menuVisible.value = false;
  if (item.callBack) {
    item.callBack();
  } else {
    openLink(item.url);
  }
};

// 刷新余额
const isRefresh = ref(false);
const refreshBalance = async () => {
  if (isRefresh.value) return;
  isRefresh.value = true;
  await userStore.getUserInfo();
  isRefresh.value = false;
};
</script>

<style scoped lang="less" src="@/assets/css/components/header.less"></style>
