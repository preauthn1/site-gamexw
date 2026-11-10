<template>
  <div class="aspect-ratio-box" :style="`padding-bottom:${runStr(props.aspect)}`" @click="$emit('click')">
    <div class="aspect-ratio-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
const runStr = (code: string) => {
  return eval(code) * 100 + "%";
};
const props = defineProps({
  aspect: {
    default: "1/1"
  }
});
</script>

<style scoped lang="less">
.aspect-ratio-box {
  position: relative;
  padding-bottom: 100%;
  height: 0;
}

.aspect-ratio-content {
  position: absolute;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
}
</style>