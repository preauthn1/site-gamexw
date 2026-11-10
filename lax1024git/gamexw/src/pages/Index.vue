<template>
  <div>
    <DownTips class="m-hide"></DownTips>
    <Banner></Banner>
    <JackPot></JackPot>
    <Notice></Notice>
    <GameList></GameList>
    <!-- <FloatBox></FloatBox> -->
  </div>
</template>

<script setup lang="ts">
import Banner from "@/components/page/index/Banner.vue";
import DownTips from "@/components/page/index/DownTips.vue";
import GameList from "@/components/page/index/GameList.vue";
import JackPot from "@/components/page/index/JackPot.vue";
import Notice from "@/components/page/index/Notice.vue";
/* import FloatBox from "@/components/page/index/FloatBox.vue";*/
</script>

<style scoped lang="less"></style>