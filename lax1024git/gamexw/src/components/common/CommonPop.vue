<template>
  <component :is="item.component" v-bind="item.data" v-for="item in componentsActive" :key="item.name"
    @closed="closed(item.name)">
  </component>
</template>

<script setup lang="ts">
import { componentsActive } from "@/router/popRouter";

// 组件需要销毁的操作
const closed = (name: string) => {
  const i = componentsActive.value.findIndex(item => item.name === name);
  componentsActive.value.splice(i, 1);
};
</script>

<style scoped lang="less"></style>