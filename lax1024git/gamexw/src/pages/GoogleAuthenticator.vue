<template>
  <div class="page-box">
    <NavBar :title="$t('谷歌身份验证器')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="text1">{{ $t("链接谷歌身份验证器") }}</div>
      <div class="text2">{{ $t("第1步：下载谷歌身份验证器") }}</div>
      <div class="text3">{{ $t("在各应用商店中搜索“Google Authenticator”ji即可下载应用") }}</div>
      <div class="text4">{{ $t("第2步：打开身份验证器应用程序并添加二维码") }}</div>
      <t-img :src="data?.data" class="code-img" />
      <div class="text5">
        <span v-if="!isKey" @click="isKey = true">{{ $t("无法读取，输入密匙") }}</span>
        <span v-else>{{ data?.secret }} <t-svg name="copy" @click="$copy(data?.secret)"></t-svg></span>
      </div>
      <div class="text6">{{ $t("第3步：在Authenticator应用程序中输入六位数字进行验证") }}</div>


      <el-form ref="formRef" :model="form" :rules="rules">
        <el-form-item prop="code">
          <el-input size="large" v-model="form.code" :placeholder="$t('输入谷歌验证码')" maxlength="6" inputmode="numeric">
            <template #prefix>
              <t-svg name="g" class="input-icon"></t-svg>
            </template>
            <template #suffix>
              <span class="suffix" @click="paste(text => form.code = text)">{{ $t("粘贴") }}</span>
            </template>
          </el-input>
        </el-form-item>
        <el-button type="primary" size="large" class="full btn" @click="submit" :loading="btnLoading">{{ $t("提交")
          }}</el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { my_bind_google_api, my_google_api } from "@/api/user";
import NavBar from "@/components/common/NavBar.vue";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import useStore from "@/store";
import { GoogleData } from "@/types/api/user";
import paste from "@/utils/paste";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
const { userStore } = useStore();
const $router = useRouter();
const isKey = ref(false);
const data: Ref<GoogleData | null> = ref(null);
const getData = async () => {
  const res = await my_google_api();
  if (res.code === ResCode.success) {
    data.value = res;
  }
};

const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  code: ""
});
const rules = {
  code: [
    {
      validator: (
        _rule: unknown,
        value: string,
        callback: (err?: Error) => void
      ) => {
        if (!/^\d{6}$/.test(value)) {
          callback(new Error("6位数字验证码"));
        }
        callback();
      },
      trigger: "change",
    },
  ]
};

const btnLoading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  btnLoading.value = true;
  const res = await my_bind_google_api(form.value);
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    userStore.getUserInfo();
    ElMessage({
      type: "success",
      message: lang.t("绑定成功")
    });
    $router.back();
  }
};

onMounted(() => {
  getData();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/googleAuthenticator.less"></style>