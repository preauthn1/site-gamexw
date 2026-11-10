<template>
  <div class="page-box">
    <div class="game-box" v-loading="loading">
      <div class="back-warp" @click="back">
        <t-svg name="close"></t-svg>
      </div>
      <iframe class="iframe" :src="url" frameborder="0" v-if="url && isLink"></iframe>
      <iframe class="iframe" :srcdoc="url" frameborder="0" v-else-if="url && !isLink"></iframe>
      <t-svg name="error" class="error-img" v-else-if="isError"></t-svg>
    </div>
  </div>
</template>

<script setup lang="ts">
import { game_login_game_api } from "@/api/games";
import { GamePlatform } from "@/enum/GamePlatform";
import { ResCode } from "@/enum/ResultCode";
import useStore from "@/store";
import { onActivated, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
const $route = useRoute();
const $router = useRouter();
const { systemStore } = useStore();
const url = ref("");
const isError = ref(false);
const loading = ref(false);
const isLink = ref(true);
const getUrl = async () => {
  isError.value = false;
  loading.value = true;
  url.value = "";
  const res = await game_login_game_api({ gid: $route.params.id as string, platform: systemStore.isPhone ? GamePlatform.phone : GamePlatform.pc });
  loading.value = false;
  if (res.code === ResCode.success) {
    url.value = res.data;
    isLink.value = url.value.startsWith("http");
  } else {
    isError.value = true;
  }
};
onActivated(() => {
  getUrl();
});

const back = () => {
  $router.back();
  setTimeout(() => {
    if ($route.path.includes("gameInfo")) back();
  }, 20);
};
</script>

<style scoped lang="less" src="@/assets/css/pages/gameInfo.less"></style>