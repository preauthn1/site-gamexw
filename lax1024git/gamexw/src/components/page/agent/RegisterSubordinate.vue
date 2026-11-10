<template>
  <div class="card-box">
    <el-form :model="form" :rules="rules" ref="formRef">
      <el-form-item prop="username">
        <el-input size="large" v-model="form.username" :placeholder="$t('输入邮箱/手机号码')">
          <template #prefix>
            <t-svg name="user" class="input-icon"></t-svg>
          </template>
        </el-input>
      </el-form-item>
      <el-form-item prop="password">
        <el-input size="large" v-model="form.password" :placeholder="$t('输入密码')" type="password">
          <template #prefix>
            <t-svg name="pass" class="input-icon"></t-svg>
          </template>
        </el-input>
      </el-form-item>
      <el-button type="primary" size="large" class="full btn" @click="submit" :loading="btnLoading">{{ $t("提交注册")
        }}</el-button>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { v2_team_childAccount_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import { passwordRule, usernameAndPhoneRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, ref } from "vue";

const formRef: Ref<FormInstance | null> = ref(null);

const form = ref({
  username: "",
  password: ""
});

const rules = {
  username: usernameAndPhoneRule(),
  password: passwordRule()
};


const btnLoading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  btnLoading.value = true;
  const res = await v2_team_childAccount_api(form.value);
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    formRef.value?.resetFields();
    ElMessage({
      message: lang.t("注册下属成功"),
      type: "success",
    });
  }
};

</script>

<style scoped lang="less" src="@/assets/css/pages/agent/registerSubordinate.less"></style>