<template>
  <div class="t-img-box">
    <img v-bind="$attrs" v-show="!loading" @load="load" class="img" />
    <div class="img-placeholder" v-if="loading">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
        <path fill="currentColor"
          d="M96 896a32 32 0 0 1-32-32V160a32 32 0 0 1 32-32h832a32 32 0 0 1 32 32v704a32 32 0 0 1-32 32zm315.52-228.48-68.928-68.928a32 32 0 0 0-45.248 0L128 768.064h778.688l-242.112-290.56a32 32 0 0 0-49.216 0L458.752 665.408a32 32 0 0 1-47.232 2.112M256 384a96 96 0 1 0 192.064-.064A96 96 0 0 0 256 384">
        </path>
      </svg>
    </div>
  </div>

</template>

<script setup lang="ts">
import { ref } from "vue";
const loading = ref(true);
const load = () => {
  loading.value = false;
};
</script>

<style scoped lang="less">
.img {
  width: inherit;
  height: inherit;
  object-fit: inherit;
  border-radius: inherit;
}

.t-img-box {
  --color: var(--color3);
  overflow: hidden;
}

.img-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--border-radius);
  animation: loading 1.4s ease infinite;
  background-position: 100% 50%;
  height: inherit;
  width: inherit;
  background: linear-gradient(90deg, var(--color) 25%, var(--color2) 37%, var(--color) 63%);
  background-size: 400% 100%;
}

@keyframes loading {
  0% {
    background-position: 100% 50%;
  }

  100% {
    background-position: 0 50%;
  }
}

svg {
  color: rgba(255, 255, 255, 0.3);
  fill: currentColor;
  height: 22%;
  width: 22%;
}
</style>