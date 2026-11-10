<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("语言") }}</div>
    <div class="lang-item" v-for="item in systemStore.systemData?.alllang" :key="item.value"
      @click="lang.setLocale(item.value, true)">{{ item.label }}</div>
  </Popup>
</template>

<script setup lang="ts">
import lang from "@/lang";
import useStore from "@/store";
import { ref, onMounted } from "vue";
const { systemStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);


onMounted(async () => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/changeLangSelectPop.less"></style>