<template>
  <div class="navbar-warp">
    <div class="navbar-box">
      <div class="back-box" @click="$router.back" v-if="props.isBack">
        <t-svg name="left" class="left-icon"></t-svg>
        <span class="m-hide">{{ $t("返回") }}</span>
      </div>
      <div class="title-box">
        <div class="left-line"></div>
        <span class="title">{{ props.title }}</span>
        <div class="right-line"></div>
      </div>
      <slot name="right"></slot>
    </div>
  </div>

</template>

<script setup lang="ts">
import { useRouter } from "vue-router";
const $router = useRouter();
const props = defineProps({
  isBack: {
    type: Boolean,
    default: true
  },
  title: {
    type: String
  }
});
</script>

<style scoped lang="less" src="@/assets/css/components/navBar.less"></style>