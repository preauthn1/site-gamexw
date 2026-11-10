<template>
  <div class="foot">
    <div class="container">
      <div class="menu-list">
        <div class="menu-item-box">
          <div class="menu-title">{{ $t("赌场") }}</div>
          <div class="menu-link" @click="$router.push('/promotion/activity/all')">{{ $t("活动") }}</div>
          <div class="menu-link" @click="$router.push('/promotion/vip')">{{ $t("贵宾") }}</div>
          <div class="menu-link" @click="$router.push('/activityShare')">{{ $t("邀请") }}</div>
        </div>
        <div class="menu-item-box">
          <div class="menu-title">{{ $t("游戏") }}</div>
          <div class="menu-link" v-for="item in gameTypeStore.defData" :key="item.id" @click="toHome(item)">{{
            $t(item.name)
            }}</div>
        </div>
        <div class="menu-item-box">
          <div class="menu-title">{{ $t("支持") }}</div>
          <div class="menu-link" @click="$router.push('/msgCenter?current=0')">{{ $t("在线支持") }}</div>
          <div class="menu-link" @click="$router.push('/msgCenter?current=0')">{{ $t("帮助中心") }}</div>
          <div class="menu-link" @click="$router.push('/msgCenter?current=4')">{{ $t("反馈") }}</div>
        </div>
        <div class="desc-box">
          <div class="icon-list-box">
            <img :src="item.icon_url" class="sevice-icon" @click="$openLink(item.href)"
              v-for="item, index in indexMenuStore.menuData?.wg_footer_service" :key="index">
          </div>
          <div class="desc">
            {{ $t("世界上最好的在线老虎机") }}
            <span class="host">{{ host }}</span>
            {{
              $t("该集团是世界上最著名的在线赌场运营公司，提供现场娱乐、国际象棋、电子、钓鱼、Iota、体育等的城市。该集团由库拉索政府授权和监管，并根据颁发给777/JAZ的许可证（编号：Antillephone）运营。集团已通过所有监管审核，并合法授权经营所有博彩及博彩游戏。")
            }}
          </div>
        </div>
      </div>
      <swiper-container class="swiper" :loop="true" :breakpoints="{
        0: { slidesPerView: 'auto' }, 768: { slidesPerView: 'auto' }
      }" :autoplay="{ delay: 3000, }">
        <swiper-slide class="slider" v-for="item, index in imgList" :key="index">
          <img :src="$require(item)" class="swiper-img">
        </swiper-slide>
      </swiper-container>
      <div class="copyright-box">
        <div class="copyright-host">{{ host }}</div>
        <div class="copyright">Copyright © 2002-202</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { GameTypeItem } from "@/types/api/games";

const {
  homeActveStore,
  gameTypeStore,
  indexMenuStore
} = useStore();
const host = window.location.host;
const imgList = [
  "pg.png",
  "jdb.png",
  "tada.png",
  "cq9.png",
  "pg.png",
  "jdb.png",
  "tada.png",
  "cq9.png",
  "pg.png",
  "jdb.png",
  "tada.png",
];
const toHome = (data: GameTypeItem) => {
  const i = gameTypeStore.data.findIndex(item => item.name === data.name);
  homeActveStore.changeActive(i);
};
</script>

<style scoped lang="less" src="@/assets/css/components/foot.less"></style>