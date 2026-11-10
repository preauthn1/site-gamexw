<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ data?.title }}</div>
    <div class="content html" v-html="data?.content"></div>
    <el-button class="full btn" type="primary" size="large" @click="isShow = false">{{ $t('阅读并理解') }}</el-button>
  </Popup>
</template>

<script setup lang="ts">
import { rule_index_api } from "@/api";
import { ResCode } from "@/enum/ResultCode";
import { Rule } from "@/types/api";
import Loading from "@/utils/loading";
import { ref, onMounted, Ref } from "vue";
const isShow = ref(false);
const emit = defineEmits(["closed"]);

const props = defineProps<{
  type?: number | string
}>();

const data: Ref<Rule | null> = ref(null);
const getDate = async () => {
  if (props.type === undefined) return;
  const res = await rule_index_api({ type: props.type });
  if (res.code === ResCode.success) {
    data.value = res.data;
  }
};
onMounted(async () => {
  Loading.show();
  await getDate();
  Loading.hide();
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/rule.less"></style>