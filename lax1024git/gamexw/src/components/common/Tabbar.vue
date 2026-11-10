<template>
  <div class="pc-hide tabbar-box">
    <div class="tabbar-content">
      <div class="tabbar-item" v-for="item, index in tabsList" :key="index" @click="$openLink(item.url)"
        :class="{ active: $route.path.startsWith(item.url) }">
        <t-svg :name="item.activeIcon" class="item-icon animate" v-if="$route.path.startsWith(item.url)"></t-svg>
        <t-svg :name="item.icon" class="item-icon " v-else></t-svg>
        <span class="itme-name">{{ $t(item.name) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const tabsList = [
  { name: "首页", icon: "Inicio-def", activeIcon: "Inicio", url: "/home" },
  { name: "晋升", icon: "Promotion-def", activeIcon: "Promotion", url: "/promotion" },
  { name: "订金", icon: "Depósito-def", activeIcon: "Depósito", url: "/deposit" },
  { name: "代理人", icon: "Agente-def", activeIcon: "Agente", url: "/agent/shareLink" },
  { name: "轮廓", icon: "Perfil-def", activeIcon: "Perfil", url: "/userCenter" },
];

</script>

<style scoped lang="less" src="@/assets/css/components/tabbar.less"></style>