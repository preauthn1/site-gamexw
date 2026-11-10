<template>
  <div class="turnrable-box">
    <div class="content" :style="{ transform: `rotate(${rotateDeg}deg)` }" @transitionend="transitionend">
      <img :src="activeData.content" class="content-bg">
      <div>
        <div class="content-item" v-for="item, index in prizeList" :key="index" :style="{ color: activeData.color }">
          <img :src="$require('turnrable-active.png')" class="active-img" v-if="false">
          <span class="content-item-num" v-if="item.type === 1">{{ Number(item.amount) }}</span>
          <img :src="item.pic" class="content-item-icon" />
        </div>
      </div>
    </div>
    <img :src="activeData.border" class="border-img">
    <div class="btn" @click="emit('start')">
      <img :src="$require('turnrable-btn.png')" class="btn-img">
      <span>{{ $t("旋转") }}</span>
    </div>
    <div class="mask" v-if="loading"></div>
  </div>
</template>

<script setup lang="ts">
import { TurntableType } from "@/enum/TurntableType";
import lang from "@/lang";
import { Lotteryconfig } from "@/types/api/action";
import { ValueOf } from "@/types/utils";
import { require } from "@/utils/$require";
import { ElMessage } from "element-plus";
import { computed, ref } from "vue";

const typeData = {} as Record<ValueOf<typeof TurntableType>, { content: string, border: string, color: string }>;
typeData[TurntableType.silver] = {
  content: require("turnrable-content.png"),
  border: require("turnrable-border.png"),
  color: "#67AA96"
};
typeData[TurntableType.golden] = {
  content: require("turnrable-content3.png"),
  border: require("turnrable-border3.png"),
  color: "#915839"
};
typeData[TurntableType.violet] = {
  content: require("turnrable-content2.png"),
  border: require("turnrable-border2.png"),
  color: "#915839"
};
const activeData = computed(() => typeData[props.type]);
const props = defineProps<{
  type: ValueOf<typeof TurntableType>,
  data: Lotteryconfig
}>();


const prizeLength = 10;

// 取出10个奖品展示，数据多的会剔除掉，少了会随机补
const prizeList = computed(() => {
  const arr = [] as Lotteryconfig["Prize"];
  if (props.data.Prize.length > prizeLength) {
    ElMessage({
      type: "warning",
      message: lang.t("礼物做多{length}个，请联系管理员", { length: prizeLength })
    });
  }

  for (let i = 0; i < prizeLength; i++) {
    arr.push(props.data.Prize[i % props.data.Prize.length]);
  }
  return arr;
});



// 转盘逻辑
const rotateDeg = ref(0);
const loading = ref(false);
let baseDeg = 0;
const start = (id) => {
  loading.value = true;
  const i = prizeList.value.findIndex(item => item.id === id);
  const activeDeg = 360 / prizeLength * i;
  baseDeg += 3600;
  rotateDeg.value = -activeDeg + baseDeg;
};
const transitionend = () => {
  loading.value = false;
  emit("end");
};

const emit = defineEmits(["start", "end"]);
defineExpose({
  start,
  loading
});
</script>

<style scoped lang="less" src="@/assets/css/components/turnrable.less"></style>