<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="top-card">
      <div class="content">
        <div class="title">
          <div class="title-content">{{ $t("获取兑换码") }}</div>
          <img :src="$require('liwu.png')" class="liwu-icon">
        </div>
        <el-form ref="formRef" :model="form" :rules="rules">
          <el-form-item prop="code">
            <el-input :placeholder="$t('输入兑换码')" size="large" v-model="form.code">
              <template #prefix>
                <t-svg name="duihuan" class="input-icon"></t-svg>
              </template>
              <template #suffix>
                <div class="send-code" @click="paste(text => form.code = text)">
                  {{ $t("粘贴") }}
                </div>
              </template>
            </el-input>
          </el-form-item>
          <div class="tips">
            <div>{{ $t("温馨提示：需存入1.00才能提取所获得的利润！") }}</div>
          </div>
          <el-button class="full" type="primary" size="large" @click="submit" :loading="loading">{{ $t("兑换奖金")
            }}</el-button>
        </el-form>
      </div>
    </div>
    <div class="rule-box">
      <div class="rule-title">{{ $t("活动详情") }}</div>
      <div>{{ $t("兑换码介绍") }}</div>
    </div>
  </Popup>
</template>

<script setup lang="ts">
import { code_receive_api } from "@/api/code";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import useStore from "@/store";
import paste from "@/utils/paste";
import { ElMessage, FormInstance } from "element-plus";
import { ref, onMounted, Ref } from "vue";
const { userStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  code: ""
});
const rules = {
  code: [
    {
      required: true,
      message: lang.t("请输入邀请码"),
      trigger: "change",
    }
  ]
};

const loading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  loading.value = true;
  const res = await code_receive_api(form.value);
  loading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: lang.t("兑换成功")
    });
    formRef.value?.resetFields();
    userStore.getUserInfo();
  }
};
</script>

<style scoped lang="less" src="@/assets/css/components/redemptionCode.less"></style>