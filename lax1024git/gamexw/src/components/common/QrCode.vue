<template>
  <t-img :src="src" @click="downLoad" />
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import QRCode from "qrcode";
const src = ref("");
const props = defineProps<{
  value?: string
}>();
const init = () => {
  QRCode.toDataURL(props.value, { margin: 0 }, function (_error, url: string) {
    src.value = url;
  });
};
const downLoad = () => {
  const a = document.createElement("a");
  a.href = src.value;
  a.download = "my-share-code.png";
  a.click();
  a.remove();
};
watch(() => props.value, (v) => {
  if (v) init();
}, { immediate: true });

defineExpose({
  downLoad
});
</script>

<style scoped lang="less"></style>