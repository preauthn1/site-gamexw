<template>
  <div class="empty-box" v-loading="props.loading">
    <t-svg name="none" class="none-icon" :class="{ opacity: props.loading || props.opacity }"></t-svg>
    <div class="desc" :class="{ opacity: props.loading || props.opacity }">{{ $t(props.msg) }}</div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps({
  msg: {
    type: String,
    default: "暂无数据"
  },
  loading: {
    default: false
  },
  // 隐藏位置，保留控件
  opacity: {
    default: false
  }
});
</script>

<style scoped lang="less">
.none-icon {
  font-size: 135px;

  .md({
    font-size: 160px;
  });
}

.empty-box {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
  flex-direction: column;
  text-align: center;
}

.desc {
  padding: 10px 0;
  margin-top: 20px;
  font-size: 18px;

  .md({
    font-size: 20px;
  });
}

.opacity {
  opacity: 0;
}
</style>