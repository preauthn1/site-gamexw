<template>
  <el-skeleton animated :loading="horseRaceLampStore.loading" style="--color:var(--color1)">
    <template #template>
      <el-skeleton-item variant="rect" class="notice-box notice-skeleton" />
    </template>

    <div class="notice-box" v-if="horseRaceLampStore.data.length" @click="tabItem">
      <img :src="$require('notice.png')" class="notice-icon">
      <NoticeBar background="none" scrollable class="notice-bar" @replay="next">
        <span>{{ horseRaceLampStore.data[active]?.title }}</span>
      </NoticeBar>
      <t-svg name="msg" class="msg-icon"></t-svg>
    </div>
  </el-skeleton>

</template>

<script setup lang="ts">
import useStore from "@/store";
import { NoticeBar } from "vant";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
const $router = useRouter();
const { horseRaceLampStore } = useStore();
// 当前在跑的文本
const active = ref(0);
const next = () => {
  active.value++;
  if (active.value === horseRaceLampStore.data.length) active.value = 0;
};

// 点击事件
const tabItem = () => {
  $router.push(`/msgCenter?id=${horseRaceLampStore.data[active.value].id}&current=3`);
};

// 获取数据
onMounted(() => {
  horseRaceLampStore.getData();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/index/notice.less"></style>