<template>
  <div class="inputs">
    <div :class="['input-item',
      index == data.length ? 'input-item-active' : '',
      data[index] ? 'input-placehoder' : '']" v-for="item, index in 6" :key="item">
    </div>
    <el-input class="input" inputmode="numeric" type="text" v-model="data" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
const data = ref("");
const props = defineProps({
  value: String
});
const emits = defineEmits(["update:value"]);
watch(data, (v, oldV) => {
  if (!/^[0-9]{0,6}$/.test(v)) {
    data.value = oldV || "";
  }
  emits("update:value", data.value);
}, { immediate: true });

watch(() => props.value, (v) => {
  (data.value = v || "");
}, { immediate: true });

</script>

<style scoped lang="less">
.inputs {
  display: flex;
  align-items: center;
  position: relative;
  justify-content: space-between;
  background-color: var(--color9);
  border-radius: 5px;
  overflow: hidden;
  border: 1px solid var(--color12);
  transition: all .2s;
  width: 100%;

  &:focus-within {

    border: 1px solid var(--color4);

    .input-item-active::after {
      display: block;
      content: "";
      width: 2px;
      height: 40%;
      background-color: var(--color2);
      animation: active 1s infinite linear;
    }
  }
}

.input {
  position: absolute;
  width: 100%;
  height: 100%;
  left: 0;
  right: 0;
  opacity: 0;
}

.input-item {
  width: 100px;
  height: 90px;
  border-left: 1px solid var(--color12);
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;

  &.input-placehoder::after {
    display: block;
    content: "";
    width: 10px;
    height: 10px;
    background-color: var(--color2);
    border-radius: 50%;
  }

  &:first-child {
    border: none;
  }
}

@keyframes active {
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