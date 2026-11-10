<template>
  <div class="content-box">
    <el-skeleton animated :loading="horseRaceLampStore.loading" v-if="$route.query.id" :rows="10">
      <div class="detail html"
        v-html="horseRaceLampStore.data.find(item => item.id === Number($route.query.id))?.content">
      </div>
    </el-skeleton>

    <template v-else>
      <el-skeleton animated :loading="horseRaceLampStore.loading">
        <template #template>
          <div class="list-box">
            <el-skeleton-item variant="rect" class="list-item notice-skeleton" v-for="i in 10" :key="i" />
          </div>
        </template>
        <div class="list-box">
          <div class="list-item" @click="toInfo(item)" v-for="item in horseRaceLampStore.data" :key="item.id">
            <img :src="$require('notice.png')" class="notice-icon">
            <div class="item-text">{{ item.title }}</div>
            <t-svg name="right"></t-svg>
          </div>
        </div>
        <Empty class="empty" v-if="false"></Empty>
      </el-skeleton>

    </template>

  </div>
</template>

<script setup lang="ts">
import Empty from "@/components/common/Empty.vue";
import useStore from "@/store";
import { NoticeListApiItem } from "@/types/api";
import { onMounted } from "vue";
import { useRouter } from "vue-router";
const { horseRaceLampStore } = useStore();
const $router = useRouter();
const toInfo = (item: NoticeListApiItem) => {
  $router.push(`?current=3&id=${item.id}`);
};

// 获取数据
onMounted(() => {
  horseRaceLampStore.getData();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/msgCenter/scrollPanel.less"></style>