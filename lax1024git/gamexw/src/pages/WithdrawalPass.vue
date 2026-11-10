<template>
  <div class="page-box">
    <NavBar :title="$t('提现密码')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="input-title">{{ $t("设置提现密码") }}</div>
      <el-form label-position="top" :model="form" :rules="rules" ref="formRef">
        <el-form-item :label="$t('旧提现密码')" prop="owithdrawal_psd" class="top-input"
          v-if="userStore.userInfo?.withdrawal_psd">
          <PayPassWordInput v-model:value="form.owithdrawal_psd"></PayPassWordInput>
        </el-form-item>
        <el-form-item :label="$t('新提现密码')" prop="withdrawal_psd" class="top-input">
          <PayPassWordInput v-model:value="form.withdrawal_psd"></PayPassWordInput>
        </el-form-item>
        <el-form-item :label="$t('确认提现密码')" prop="agent_withdrawal_psd">
          <PayPassWordInput v-model:value="form.agent_withdrawal_psd"></PayPassWordInput>
        </el-form-item>
        <div class="tip">{{ $t("注意：提现密码保护您的资金，极其重要，保守秘密以避免任何经济损失。") }}</div>
        <el-button class="btn full" size="large" type="primary" @click="submit()" :loading="loading">{{ $t("提交")
          }}</el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { my_pay_password_api } from "@/api/user";
import NavBar from "@/components/common/NavBar.vue";
import PayPassWordInput from "@/components/common/PayPassWordInput.vue";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import useStore from "@/store";
import { openLink } from "@/utils/openLink";
import { payPasswordAgainRule, payPasswordRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, ref } from "vue";
import { useRouter } from "vue-router";
const { userStore } = useStore();
const $router = useRouter();
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  owithdrawal_psd: "",
  withdrawal_psd: "",
  agent_withdrawal_psd: ""
});

const rules = {
  owithdrawal_psd: payPasswordRule(),
  withdrawal_psd: payPasswordRule(),
  agent_withdrawal_psd: payPasswordAgainRule(() => form.value.withdrawal_psd),
};

const loading = ref(false);
const submit = async (code?: string, tips = false) => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  loading.value = true;
  const res = await my_pay_password_api({
    opassword: form.value.owithdrawal_psd,
    password: form.value.withdrawal_psd,
    verify_code: code
  }, tips);
  loading.value = false;
  if (res.code === ResCode.success) {
    formRef.value?.resetFields();
    ElMessage({
      type: "success",
      message: lang.t("提现密码设置成功")
    });
    $router.back();
    userStore.getUserInfo();
    return true;
  } else if (res.code === ResCode.verificationCode) {
    openLink("/sendCodePop", {
      callback: async (code: string) => await submit(code, true)
    });
  } else {
    ElMessage({
      type: "error",
      message: res.msg
    });
  }
};
</script>

<style scoped lang="less" src="@/assets/css/pages/withdrawalPass.less"></style>