<template>
  <div class="more-box" v-if="!props.loading" @click="loadmore">
    <!-- <span>{{ $t("显示42款游戏中的10款") }}</span> -->
    <span class="more">{{ $t("加载更多") }}</span>
    <t-svg name="bottom"></t-svg>
  </div>
  <div class="more-box" v-else>
    <span class="more" @click="loadmore">{{ $t("加载中") }}</span>
    <el-icon>
      <Loading />
    </el-icon>
  </div>
</template>

<script setup lang="ts">
import { Loading } from "@element-plus/icons-vue";
const emit = defineEmits(["loadmore"]);
const loadmore = () => {
  emit("loadmore");
};
const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  }
});
</script>

<style scoped lang="less" src="@/assets/css/components/more.less"></style>