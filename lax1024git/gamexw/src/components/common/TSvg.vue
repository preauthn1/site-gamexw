<template>
  <svg focusable="false" aria-hidden="true" :style="svgStyle">
    <use :href="path" class="svg-use"></use>
  </svg>
</template>

<script setup lang="ts">
import { computed } from "vue";
const props = defineProps({
  name: String,
  path: String,
  color: {
    type: String,
    default: "currentColor", //继承字体颜色
  },
  size: {
    type: String,
    default: "1em", //继承字体大小
  },
  width: {
    type: String,
    default: "",
  },
  height: {
    type: String,
    default: "",
  }
});
const path = computed(() => {
  return `#icon-${props.name}`;
});
const svgStyle = computed(() => {
  return {
    width: props.width || props.size,
    height: props.height || props.size,
    fill: props.color,
  };
});
</script>

<style scoped lang="less"></style>
