<template>
  <div class="item-box">
    <div class="item-content">
      <div class="new" v-if="isNew">NEW</div>
      <div class="time" v-if="props.time">{{ props.time }}</div>
      <div class="title" v-if="props.title">{{ props.title }}</div>
      <div class="content html" v-if="props.content" v-html="props.content"></div>
    </div>
    <div class="item-bottom" v-if="$slots.bottom">
      <slot name="bottom"></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  title?: string
  time?: string
  content?: string
  isNew?: boolean
}>();
</script>

<style scoped lang="less" src="@/assets/css/components/msgItem.less"></style>