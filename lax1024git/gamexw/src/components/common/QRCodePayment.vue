<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">
      <span>{{ $t("二维码收款地址") }}</span>
    </div>

    <t-img :src="poprs.data.qrcode_url" class="code-img"></t-img>
    <el-input size="large" :value="poprs.data.address" readonly class="input">
      <template #suffix>
        <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.address)"></t-svg>
      </template>
    </el-input>
  </Popup>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { RechargeApply } from "@/types/api/recharge";
const isShow = ref(false);
const emit = defineEmits(["closed"]);
const poprs = defineProps<{
  data: RechargeApply
}>();

onMounted(() => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/qRCodePayment.less"></style>