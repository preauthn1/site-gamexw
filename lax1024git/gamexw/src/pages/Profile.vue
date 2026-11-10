<template>
  <div class="page-box">
    <NavBar :title="$t('轮廓')" class="nav-bar"></NavBar>
    <div class="user-box">
      <el-form label-position="top" ref="formRef" :model="form" :rules="rules">
        <div class="avatar-wrap">
          <div class="avatar-box">
            <t-img :src="userStore.userInfo?.head_img" class="avatar" />
            <div class="user-label">{{ userStore.userInfo?.level_name }}</div>
          </div>
          <div>
            <div class="setting-avatar" @click="$openLink('/changeAvatar')">{{ $t("设置") }}</div>
            <div class="id-warp">
              <span>ID：{{ userStore.userInfo?.rec_code }}</span>
              <t-svg name="copy" class="copy-icon" @click="$copy(userStore.userInfo?.rec_code)"></t-svg>
            </div>
          </div>
        </div>
        <el-form-item>
          <el-input size="large" disabled :value="userStore.userInfo?.yphone || userStore.userInfo?.mail">
            <template #prefix>
              <t-svg name="user" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-input size="large" disabled :placeholder="$t('链接到您的手机')"
            :value="`+${userStore.userInfo?.qh} ${userStore.userInfo?.yphone}`">
            <template #prefix>
              <t-svg name="shouji2" class="input-icon"></t-svg>
            </template>
            <template #suffix>
              <div class="link" @click="$router.push('/phone')" v-if="!userStore.userInfo?.phone">{{ $t("链接") }}</div>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-input size="large" disabled :placeholder="$t('链接到您的电子邮件')" :value="userStore.userInfo?.mail">
            <template #prefix>
              <t-svg name="youxiang" class="input-icon"></t-svg>
            </template>
            <template #suffix>
              <div class="link" @click="$router.push('/email')" v-if="!userStore.userInfo?.mail">{{ $t("链接") }}</div>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="whatsapp">
          <el-input size="large" :placeholder="$t('输入您的Whatsapp账号')" v-model="form.whatsapp">
            <template #prefix>
              <t-svg name="whatsapp" class="input-icon" style="transform: scale(.8);"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="facebook">
          <el-input size="large" :placeholder="$t('输入您的Facebook账号')" v-model="form.facebook">
            <template #prefix>
              <t-svg name="facebook" class="input-icon" style="transform: scale(.8);"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="telegram" class="input-bottom">
          <el-input size="large" :placeholder="$t('输入您的Telegram账号')" v-model="form.telegram">
            <template #prefix>
              <t-svg name="telegram" class="input-icon" style="transform: scale(.8);"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item prop="birthday_d">
          <template #label>
            <span>{{ $t("选择出生日期") }}</span>
            <span>（{{ $t("一旦设置，无法修改") }}）</span>
          </template>
          <div class="time-box">
            <el-select size="large" v-model="form.birthday_d" :placeholder="$t('日')"
              :disabled="Boolean(userStore.userInfo?.birthday)">
              <el-option :value="item" v-for="item in YMD.d" :key="item" />
            </el-select>
            <el-select size="large" v-model="form.birthday_m" :placeholder="$t('月')"
              :disabled="Boolean(userStore.userInfo?.birthday)">
              <el-option :value="item" v-for="item in YMD.m" :key="item" />
            </el-select>
            <el-select size="large" v-model="form.birthday_y" :placeholder="$t('年')"
              :disabled="Boolean(userStore.userInfo?.birthday)">
              <el-option :value="item" v-for="item in YMD.y" :key="item" />
            </el-select>
          </div>
        </el-form-item>
        <div class="bth-box">
          <el-button plain size="large" class="full" @click="$router.back">{{ $t("返回") }}</el-button>
          <el-button type="primary" size="large" class="full" @click="submit" :loading="loading">{{ $t("保存")
            }}</el-button>
        </div>
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
import { YMD } from "@/utils/timeDate";
import { ElMessage, FormInstance } from "element-plus";
import { Ref, ref, watch } from "vue";
const { userStore } = useStore();
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  whatsapp: "",
  telegram: "",
  facebook: "",
  birthday_d: "",
  birthday_m: "",
  birthday_y: "",
});

watch(() => userStore.userInfo, (v) => {
  if (!v) return;
  form.value.whatsapp = v.whatsapp || "";
  form.value.telegram = v.telegram || "";
  form.value.facebook = v.facebook || "";
  const birthday = v.birthday.split("-");
  form.value.birthday_y = birthday[0];
  form.value.birthday_m = birthday[1];
  form.value.birthday_d = birthday[2];
}, { immediate: true });
const rules = {
  birthday_d: [
    {
      validator: (_rule, _value, callback: (err?: Error) => void) => {
        const { birthday_y, birthday_m, birthday_d } = form.value;
        if (!birthday_d) {
          callback(new Error(lang.t("请选择出生日期：日")));
          return;
        }
        if (!birthday_m) {
          callback(new Error(lang.t("请选择出生日期：月")));
          return;
        }
        if (!birthday_y) {
          callback(new Error(lang.t("请选择出生日期：年")));
          return;
        }
        callback();
      },
      trigger: "change",
    },
  ],
  telegram: [
    {
      required: true,
      message: lang.t("请输入telegram"),
      trigger: "change",
    },
  ],
  whatsapp: [
    {
      required: true,
      message: lang.t("请输入whatsapp"),
      trigger: "change",
    },
  ],
  facebook: [
    {
      required: true,
      message: lang.t("请输入facebook"),
      trigger: "change",
    },
  ]
};
const loading = ref(false);
const submit = async () => {
  const confirm = await formRef.value?.validate();
  if (!confirm) return;
  loading.value = true;
  const res = await my_editinfo_api({
    whatsapp: form.value.whatsapp,
    telegram: form.value.telegram,
    facebook: form.value.facebook,
    birthday: `${form.value.birthday_y}-${form.value.birthday_m}-${form.value.birthday_d}`,
  });
  loading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      message: lang.t("更改成功"),
      type: "success"
    });
    userStore.getUserInfo();
  }
};
</script>

<style scoped lang="less" src="@/assets/css/pages/profile.less"></style>