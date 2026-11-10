<template>
  <div>
    <Popup v-model:show="isShow" @closed="emit('closed')" class="pop" :closeable="false">
      <div class="title">{{ $t(props.title) }}</div>
      <div class="content">
        {{ $t(props.content) }}
      </div>
      <div class="btn-list">
        <el-button class="full btn" type="primary" size="large" plain @click="isShow = false">{{ $t(props.btnCancelText)
          }}</el-button>
        <el-button class="full btn" type="primary" size="large" @click="confirm" :loading="btnLoading">{{
          $t(props.btnConfirmText)
        }}</el-button>
      </div>
    </Popup>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
const isShow = ref(false);
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});
const props = defineProps({
  content: {
    type: String,
    default: ""
  },
  title: {
    type: String,
    default: "提醒"
  },
  btnConfirmText: {
    type: String,
    default: "确认"
  },
  btnCancelText: {
    type: String,
    default: "取消"
  },
  confirm: {
    type: Function
  }
});
const btnLoading = ref(false);
const confirm = async () => {
  btnLoading.value = true;
  if (props.confirm) await props.confirm();
  btnLoading.value = false;
  isShow.value = false;
};
</script>

<style scoped lang="less" src="@/assets/css/components/confirm.less"></style>