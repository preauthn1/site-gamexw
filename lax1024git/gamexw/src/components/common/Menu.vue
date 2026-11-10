<template>
  <transition name="menu">
    <div class="menu-box" v-if="menuStore.isShow" :class="{ 'other-top-padding': downloadTipStore.isShow }">
      <el-skeleton animated :loading="gameTypeStore.loading">
        <template #template>
          <div class="top-menu-list">
            <el-skeleton-item variant="image" class="top-menu-item" v-for="i in 6" :key="i" />
          </div>
        </template>
        <template #default>
          <div class="top-menu-list">
            <div class="top-menu-item"
              :class="{ 'top-menu-item-active': homeActveStore.active === index && $route.path === '/home' }"
              v-for="(item, index) in gameTypeStore.data" :key="index" @click="toHome(index)">
              <TSvg :name="item.default_icon" class="top-menu-icon" size="" v-if="isSvgLink(item.default_icon)"></TSvg>
              <img :src="item.default_icon" class="top-menu-icon" v-else />


              <img :src="item.icon" class="top-menu-icon top-menu-icon-active" />
              <span>{{ $t(item.name) }}</span>
            </div>
          </div>
        </template>
      </el-skeleton>
      <div class="music-box">
        <div class="music-icon-box">
          <!-- 上一首 -->
          <TSvg name="houtui" class="music-icon" @click="musicStore.pre"></TSvg>
          <!-- 农房 -->
          <TSvg name="bofang" class="music-icon" @click="musicStore.play" v-if="!musicStore.isPlay"></TSvg>
          <!-- 暂停 -->
          <TSvg name="zanting" class="music-icon" v-else @click="musicStore.pause"></TSvg>
          <!-- 下一手 -->
          <TSvg name="kuaijin" class="music-icon" @click="musicStore.next"></TSvg>

          <!-- 循环 -->
          <t-svg name="xunhuan" class="music-icon" v-if="musicStore.loopState === LoopState.allLoops"
            @click="musicStore.changeLoopState"></t-svg>
          <t-svg name="suiji" class="music-icon" v-if="musicStore.loopState === LoopState.randomPlay"
            @click="musicStore.changeLoopState"></t-svg>
          <t-svg name="danqu" class="music-icon" v-if="musicStore.loopState === LoopState.singleLoop"
            @click="musicStore.changeLoopState"></t-svg>

          <el-badge :value="musicStore.myMusicListIds.length" class="item" color="var(--color1)">
            <TSvg name="bofangliebiao" class="music-icon" @click="$openLink('/music')"></TSvg>
          </el-badge>
        </div>
        <span class="music-name">{{ musicStore.currentData?.name }}</span>
      </div>
      <div class="records-box" @click="$router.push('/report?current=0')">
        <TSvg name="jilu" class="records-icon"></TSvg>
        {{ $t("投注记录") }}
      </div>
      <div class="activity-box">
        <div class="activity-item" v-for="(item, index) in activityList" :key="index"
          :style="`background: ${item.color};`" @click="
            $router.push(item.url);
          linkHideMenu();
          ">
          <img :src="$require(item.icon)" class="activity-icon" />
          <span>{{ $t(item.name) }}</span>
        </div>
      </div>
      <div class="lang-box">
        <el-popover placement="right" trigger="click">
          <div class="lang-select-item" v-for="item in systemStore.systemData?.alllang" :key="item.value"
            @click="lang.setLocale(item.value, true)">
            {{ item.label }}
          </div>
          <template #reference>
            <div class="lang-item">
              <t-svg name="lang" class="lang-item-icon"></t-svg>
              <span>{{ lang.getLocale() }}</span>
            </div>
          </template>
        </el-popover>
        <div class="lang-item" @click="downloadApp"
          v-if="envStore.getDevice() !== DeviceType.iosApp && envStore.getDevice() !== DeviceType.androidApp">
          <t-svg name="download" class="lang-item-icon"></t-svg>
          <span>{{ $t("APP 下载") }}</span>
        </div>
      </div>
    </div>
  </transition>
  <Overlay :show="menuStore.isShow" @click="menuStore.changeShow" z-index="100" class="overlay"></Overlay>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { Overlay } from "vant";
import lang from "@/lang";
import { useRouter } from "vue-router";
import { LoopState } from "@/enum/Music";
import isSvgLink from "@/utils/isSvgLink";
import downloadApp from "@/utils/downloadApp";
import { DeviceType } from "@/enum/DeviceType";
const {
  menuStore,
  downloadTipStore,
  homeActveStore,
  systemStore,
  gameTypeStore,
  musicStore,
  envStore
} = useStore();
const $router = useRouter();
const activityList = [
  {
    name: "活动",
    icon: "event.png",
    color: "linear-gradient( 180deg, #CDFFF5 0%, #82E8D3 16%, #4AD6B9 100%)",
    url: "/promotion/activity/all",
  },
  {
    name: "历史性",
    icon: "history.png",
    color: "linear-gradient( 180deg, #F3E0FF 0%, #EDC7FF 16%, #A544D7 100%)",
    url: "/promotion/history",
  },
  {
    name: "贵宾",
    icon: "vip.png",
    color: "linear-gradient( 180deg, #FFF8E0 0%, #FFF0C7 19%, #EEB20E 100%)",
    url: "/promotion/vip",
  },
  {
    name: "代理人",
    icon: "agent.png",
    color: "linear-gradient( 180deg, #FFEFCB 0%, #F8C98A 26%, #EE4545 100%)",
    url: "/agent/shareLink",
  },
];
const linkHideMenu = () => {
  if (systemStore.isPhone) {
    menuStore.changeShow();
  }
};
const toHome = (index: number) => {
  homeActveStore.changeActive(index);
  linkHideMenu();
};
</script>

<style scoped lang="less" src="@/assets/css/components/menu.less"></style>
