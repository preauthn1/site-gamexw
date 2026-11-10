<template>
  <TSvg name="shuaxin" class="refresh" :class="{isRefresh:props.isRefresh}"></TSvg>
</template>

<script setup lang="ts">
const props = defineProps<{
  isRefresh: boolean
}>();
</script>

<style scoped lang="less">
.refresh{
  animation: refresh .3s linear infinite;
  animation-play-state: paused;
}
.isRefresh{
  animation-play-state: running;
}
@keyframes refresh{
  0%{
    transform: rotate(0deg);
  }100%{
    transform: rotate(360deg);
  }
}
</style>