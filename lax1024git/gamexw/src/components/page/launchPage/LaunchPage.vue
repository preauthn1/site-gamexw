<template>
  <div class="tips-wapper" v-if="props.isShow.value">
    <t-svg name="slot" class="logo"></t-svg>
  </div>
</template>

<script setup lang="ts">
import TSvg from "@/components/common/TSvg.vue";
import { Ref } from "vue";
const props = defineProps<{
  isShow: Ref<boolean>
}>();
</script>

<style scoped lang="less">
.tips-wapper {
  position: fixed;
  z-index: 9999;
  width: 100%;
  height: 100%;
  background-color: var(--color3);
  left: 0;
  top: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  clip-path: circle(100% at 50% 50%);
}

.logo {
  width: 200px;
  font-size: 200px;
  animation: logo 3s infinite;
}

@keyframes logo {
  0% {
    opacity: 1;
  }

  50% {
    opacity: 0;
  }

  100% {
    opacity: 1;
  }
}
</style>