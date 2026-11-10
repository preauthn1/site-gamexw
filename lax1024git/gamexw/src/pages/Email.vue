<template>
  <div class="page-box">
    <NavBar :title="$t('电子邮箱')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="input-title">{{ $t("链接邮箱") }}</div>
      <el-form :model="form" :rules="rules" ref="formRef">
        <el-form-item prop="mail">
          <el-input size="large" v-model="form.mail" :placeholder="$t('请输入邮箱')">
            <template #prefix>
              <t-svg name="youxiang" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="verify_code">
          <el-input size="large" v-model="form.verify_code" :placeholder="$t('验证码')">
            <template #prefix>
              <t-svg name="key" class="input-icon"></t-svg>
            </template>
            <template #suffix>
              <div class="send-code">
                <SendCode :email="form.mail" scene="bind" :beforSend="beforSendPhone">
                </SendCode>
              </div>
            </template>
          </el-input>
        </el-form-item>
        <el-button type="primary" size="large" class="full btn" @click="submit" :loading="loading">{{ $t("提交")
          }}</el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { my_editinfo_api } from "@/api/user";
import NavBar from "@/components/common/NavBar.vue";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import useStore from "@/store";
import { phoneCodeRule, usernameRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, ref } from "vue";
import { useRouter } from "vue-router";
const { userStore } = useStore();
const $router = useRouter();
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  mail: "",
  verify_code: ""
});
const rules = {
  mail: usernameRule(),
  verify_code: phoneCodeRule(),
};
// 发送验证码前的验证
const beforSendPhone = async () => Boolean(await formRef.value?.validateField(["mail"]));

const loading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  loading.value = true;
  const res = await my_editinfo_api(form.value);
  loading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      message: lang.t("绑定成功"),
      type: "success"
    });
    $router.back();
    userStore.getUserInfo();
  }

};

</script>

<style scoped lang="less" src="@/assets/css/pages/phone.less"></style>