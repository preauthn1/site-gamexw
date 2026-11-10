<template>
  <div class="page-box">
    <NavBar :title="$t('登录密码')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="input-title">{{ $t("修改密码") }}</div>
      <el-form ref="formRef" :model="form" :rules="rules">
        <el-form-item prop="old_password">
          <el-input size="large" v-model="form.old_password" :placeholder="$t('请输入旧密码')" type="password" show-password>
            <template #prefix>
              <t-svg name="pass" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="password">
          <el-input size="large" v-model="form.password" :placeholder="$t('请输入新密码')" type="password" show-password>
            <template #prefix>
              <t-svg name="pass" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <PassLevel :REDACTED></PassLevel>
        <el-form-item prop="again_password">
          <el-input size="large" v-model="form.again_password" :placeholder="$t('确认新密码')" type="password" show-password>
            <template #prefix>
              <t-svg name="pass" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-button type="primary" size="large" class="full btn" @click="submit()" :loading="btnLoading">{{ $t("提交")
          }}</el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { my_editinfo_api } from "@/api/user";
import NavBar from "@/components/common/NavBar.vue";
import PassLevel from "@/components/common/PassLevel.vue";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import { openLink } from "@/utils/openLink";
import { passwordAgainRule, passwordRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, ref } from "vue";
import { useRouter } from "vue-router";
const $router = useRouter();
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  old_REDACTED,
  REDACTED,
  again_REDACTED
});
const rules = {
  old_password: passwordRule(),
  password: passwordRule(),
  again_password: passwordAgainRule(() => form.value.password)
};
const btnLoading = ref(false);

const submit = async (code?: string, tips = false) => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  btnLoading.value = true;
  const res = await my_editinfo_api({
    old_password: form.value.old_password,
    password: form.value.password,
    verify_code: code || ""
  }, tips);
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: lang.t("修改成功")
    });
    $router.back();
    formRef.value?.resetFields();
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

<style scoped lang="less" src="@/assets/css/pages/loginPass.less"></style>