<template>
  <div class="banner-box">
    <el-skeleton animated :loading="loading" style="--color:var(--color1)">
      <template #template>
        <swiper-container class="swiper" :effect="systemStore.isPhone ? '' : 'coverflow'" :loop="true"
          :centeredSlides="true" :breakpoints="{
            0: { slidesPerView: 'auto', spaceBetween: 10 }, 768: { slidesPerView: 'auto' }
          }" :coverflowEffect="{
            rotate: 0,
            stretch: 200,
            depth: 400,
            modifier: 1,
          }">
          <swiper-slide class="slider"> <el-skeleton-item variant="image" class="swiper-img" /></swiper-slide>
          <swiper-slide class="slider"> <el-skeleton-item variant="image" class="swiper-img" /></swiper-slide>
          <swiper-slide class="slider"> <el-skeleton-item variant="image" class="swiper-img" /></swiper-slide>
          <swiper-slide class="slider"> <el-skeleton-item variant="image" class="swiper-img" /></swiper-slide>
        </swiper-container>
      </template>
      <template #default>
        <swiper-container class="swiper" :effect="systemStore.isPhone ? '' : 'coverflow'" :loop="true"
          :centeredSlides="true" :breakpoints="{
            0: { slidesPerView: 'auto', spaceBetween: 10 }, 768: { slidesPerView: 'auto' }
          }" :autoplay="{ delay: 3000, }" :coverflowEffect="{
            rotate: 0,
            stretch: 200,
            depth: 400,
            modifier: 1,
          }" v-if="banner.length">
          <swiper-slide v-for="(item) in banner" :key="item" class="slider">
            <t-img :src="item.img" class="swiper-img" style="--color:var(--color1)" @click="$openLink(item.jump_url)" />
          </swiper-slide>
        </swiper-container>
      </template>
    </el-skeleton>

  </div>
</template>

<script setup lang="ts">
import { banner_index_api } from "@/api";
import useStore from "@/store";
import { BannerIndexApi } from "@/types/api";
import { Ref, computed, onMounted, ref } from "vue";
import lang from "@/lang";
import ApiStorage from "@/storage/ApiStorage";
const { systemStore } = useStore();

const data: Ref<BannerIndexApi | null> = ref(null);
const getData = async () => {
  const fetch = new ApiStorage({
    api: () => banner_index_api(),
    success(resData) {
      data.value = resData;
    }
  });
  await fetch.getData();
};
const banner = computed(() => {
  const key = systemStore.isPhone ? "mobile" : "pc";
  return [
    ...(systemStore.systemDataNew?.banner?.[key] || []),
    ...(data.value?.[key] || [])
  ].filter((item) => item.lang === lang.getLocale()).sort((a, b) => b.sort - a.sort);
});

const loading = ref(false);
onMounted(async () => {
  loading.value = true;
  await getData();
  loading.value = false;
});
</script>

<style scoped lang="less" src="@/assets/css/pages/index/banner.less"></style>