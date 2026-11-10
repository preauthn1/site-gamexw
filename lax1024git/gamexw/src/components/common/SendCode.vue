<template>
  <span v-if="loading">{{ $t("发送中") }}</span>
  <span v-else-if="time">{{ time }}s</span>
  <span v-else @click.stop.prevent="sendCode">{{ $t("接收代码") }}</span>
</template>

<script setup lang="ts">
import { sendcode_index_api, sendcode_mail_api } from "@/api/user";
import { ResCode } from "@/enum/ResultCode";
import { ref } from "vue";
const time = ref(0);
const props = defineProps<{
  qh?: string,
  phone?: string,
  email?: string,
  scene: "bind" | "verify" | "forget" | "register",
  beforSend?: () => boolean | Promise<boolean>
}>();
const loading = ref(false);
const sendEmailCode = async () => await sendcode_mail_api({ email: props.email || "", scene: props.scene });
const sendPhoneCode = async () => await sendcode_index_api({ qh: props.qh || "", phone: props.phone || "", scene: props.scene });
const sendCode = async () => {
  if (props.beforSend) {
    const flag = await props.beforSend();
    if (!flag) return;
  }
  loading.value = true;
  const res = await (props.phone ? sendPhoneCode : sendEmailCode)();
  loading.value = false;
  if (res.code === ResCode.success) {
    time.value = 60;
    const timeId = setInterval(() => {
      time.value--;
      if (time.value === 0) clearInterval(timeId);
    }, 1000);
  }
};
</script>

<style scoped lang="less"></style>