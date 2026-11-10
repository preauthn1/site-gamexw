<template>
  <div>

    <!-- 头部内容 -->
    <Header v-if="$route.meta.mobileHead || !systemStore.isPhone"></Header>
    <div class="page-box" :style="{ paddingLeft: menuStore.isShow ? 'var(--menu-width)' : 0 }">
      <!-- 缓存页面，做法10张界面清理 -->
      <div class="container page">
        <router-view v-slot="{ Component }">
          <!-- key 为优化二级路由，两个路由同时刷新的问题，子路由有缓存，可能还有多个子路由，需要进行路由判断，让其只存在一个路由生效 -->
          <keep-alive :max="10">
            <component :is="Component"
              :key="$route.path.split('/')[1] + JSON.stringify($route.params) + ($route.meta.longKeep || keepAliveKey)">
            </component>
          </keep-alive>
        </router-view>
      </div>


      <!-- 底部内容 -->
      <Foot v-if="$route.path === '/home' || !systemStore.isPhone"></Foot>
    </div>
    <Tabbar v-if="$route.meta.mobileTabbar && systemStore.isPhone"></Tabbar>
  </div>
</template>

<script setup lang="ts">
import Header from "@/components/common/Header.vue";
import Foot from "@/components/common/Foot.vue";
import Tabbar from "@/components/common/Tabbar.vue";
import useStore from "@/store";
import { ref, watch } from "vue";
const { menuStore, systemStore, userStore } = useStore();

// 登录状态变化，重新设置keepAliveKey
const keepAliveKey = ref(0);
watch(() => userStore.isLogin, () => keepAliveKey.value++);
</script>

<style scoped lang="less" src="@/assets/css/layout/index.less"></style>