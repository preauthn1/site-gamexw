<template>
  <div class="allActivity-box" v-if="activityStore.activityList.length">
    <div class="title m-hide">{{ $t("所有活动") }}</div>
    <!-- 轮播图 -->
    <div class="content">
      <t-svg name="left" class="ctr-icon m-hide" @click="pre"></t-svg>
      <swiper-container speed="500" :loop="false" :autoplay="false" class="swiper" slidesPerView="auto" ref="swiper">
        <swiper-slide class="swiper-slide" v-for="(item, index) in activityStore.activityList" :key="index">
          <div class="activity-item" :class="{ 'activity-item-active': index === active }" @click="tabItem(item)">
            <t-img :src="item.c_img" class="activity-cover" />
            <t-svg name="xuanzhongduigou" class="bottom-icon" size=""></t-svg>
          </div>
          <div class="activity-name pc-hide">{{ item.c_title }}</div>
        </swiper-slide>
      </swiper-container>
      <t-svg name="right" class="ctr-icon m-hide" @click="next"></t-svg>
    </div>

  </div>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { ActionItem } from "@/types/api/action";
import Swiper from "swiper";
import { openLink } from "@/utils/openLink";
import { computed, nextTick, onMounted, ref, Ref } from "vue";
import { useRoute, useRouter } from "vue-router";
const { activityStore } = useStore();
const $route = useRoute();
const $router = useRouter();
const next = () => {
  swiper.value?.swiper.slideNext();
};
const pre = () => {
  swiper.value?.swiper.slidePrev();
};
const swiper: Ref<HTMLElement & { swiper: Swiper } | null> = ref(null);
const tabItem = (item: ActionItem) => {
  if (item.jump_url) {
    openLink(item.jump_url);
  } else {
    $router.push(`/activityInfo/${item.id}`);
  }
};

const active = computed(() => {
  return activityStore.activityList.findIndex(item => item.jump_url === $route.path || item.id === Number($route.params.id));
});
onMounted(async () => {
  await activityStore.getList();
  nextTick(() => swiper.value?.swiper.slideTo(active.value));
});
</script>

<style scoped lang="less" src="@/assets/css/components/allActivity.less"></style>