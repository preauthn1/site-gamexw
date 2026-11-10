<template>
  <div class="jackpot">
    <count-up :endVal="endNum" :startVal="startNum" :decimalPlaces="2" class="number">
      <template #prefix>
        <span>R$ </span>
      </template>
    </count-up>
  </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from "vue";
import CountUp from "vue-countup-v3";
const startNum = ref(0);
const endNum = ref(0);
let timeID: number;
const satrt = () => {
  startNum.value = getNum();
  endNum.value = getNum();
  timeID = setInterval(() => {
    startNum.value = endNum.value;
    endNum.value = getNum();
  }, 3000);
};
const end = () => {
  clearInterval(timeID);
};
const getNum = () => {
  const date = new Date();
  const seconds = date.getSeconds() + date.getMinutes() * 60; // 1-3600
  // 设置伪随机输，根据时间线，算出随机数的和
  // 10-1500
  const randomArr = [1230, 1120, 1850, 106, 508, 458, 1890, 623];
  let num = 0;
  for (let i = 0; i < seconds; i++) {
    num += randomArr[i % randomArr.length];
  }
  return Date.now() / 1000 + num;
};

onMounted(() => {
  satrt();
});
onUnmounted(() => {
  end();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/index/jackpot.less"></style>
