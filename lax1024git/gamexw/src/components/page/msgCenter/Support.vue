<template>
  <div class="warp">
    <div class="user-box">
      <img :src="$require('service.webp')" class="avatar">
      <div class="user-content-box">
        <div class="user-content">
          <div class="user-title">24/7 {{ $t("在线支持") }}</div>
          <div class="user-desc">{{ $t("与专业的在线客服聊天，解决您的问题") }}</div>
        </div>
        <el-button plain @click="$openLink(indexMenuStore.menuData?.wg_service[0]?.children?.[0].href || '')">{{
          $t("在线客服")
        }}</el-button>
      </div>

    </div>

    <div class="card-box">
      <Tabs v-model:active="active" :shrink="true" class="service-tabs" animated>
        <Tab v-for="tab, index in indexMenuStore.menuData?.wg_service" :key="index">
          <template #title>
            <div class="title-box">
              <img :src="tab.icon_url" class="title-icon">
              <span>{{ tab.name }}</span>
            </div>
          </template>
          <div class="service-list">
            <div class="service-item" v-for="item, index in tab.children" :key="index">
              <img :src="item.icon_url" class="service-item-icon" />
              <div class="service-item-content">
                <div class="item-name">{{ tab.name }}</div>
                <div class="item-desc">{{ item.name }}</div>
              </div>
              <el-button type="primary" @click="$openLink(item.href)">{{ $t("现在联系") }}</el-button>
            </div>
          </div>
        </Tab>
      </Tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Tab, Tabs } from "vant";
import useStore from "@/store";
const active = ref(0);
const { indexMenuStore } = useStore();

</script>

<style scoped lang="less" src="@/assets/css/pages/msgCenter/support.less"></style>