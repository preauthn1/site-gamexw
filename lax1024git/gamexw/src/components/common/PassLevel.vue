<template>
  <div :class="`pass-level-box pass-level-box${level}`">
    <span class="pass-level-title">{{ $t("密码") }}</span>
    <div class="pass-lev-item"></div>
    <div class="pass-lev-item"></div>
    <div class="pass-lev-item"></div>
    <div class="pass-lev-item"></div>
  </div>
</template>

<script setup lang="ts">
import { computed } from "vue";

const props = defineProps<{ password: string }>();
const level = computed(() => {
  let levelNum = 0;
  if (/[A-Z]/.test(props.password)) levelNum++;
  if (/[a-z]/.test(props.password)) levelNum++;
  if (/[0-9]/.test(props.password)) levelNum++;
  if (/[^\w\d]/.test(props.password)) levelNum++;
  return levelNum;
});
</script>

<style scoped lang="less">
.pass-level-box {
  display: flex;
  align-items: center;
  margin-bottom: 12px;

  .pass-level-title {
    margin-right: 10px;
    color: var(--color7);
  }

  .pass-lev-item {
    width: 42px;
    height: 8px;
    background-color: var(--color12);
    border-radius: 100vh;
    margin-right: 10px;

    .md({
      height: 10px;
      width: 50px;
    });
}

&.pass-level-box1 {
  .pass-lev-item {
    &:nth-child(2) {
      background-color: #c12929;
    }
  }
}

&.pass-level-box2 {
  .pass-lev-item {

    &:nth-child(2),
    &:nth-child(3) {
      background-color: #fff0bb;
    }
  }
}

&.pass-level-box3 {
  .pass-lev-item {

    &:nth-child(2),
    &:nth-child(3),
    &:nth-child(4) {
      background-color: #d3df18;
    }
  }
}

&.pass-level-box4 {
  .pass-lev-item {
    background-color: #5cbf3f;
  }
}
}
</style>