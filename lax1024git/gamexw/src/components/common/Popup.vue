<template>
  <Popup round v-bind="$attrs" closeable z-index="999">
    <slot></slot>
  </Popup>
</template>

<script setup lang="ts">
import { Popup } from "vant";
</script>

<style scoped lang="less" src="@/assets/css/components/popup.less"></style>