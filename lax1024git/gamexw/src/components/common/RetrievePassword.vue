<template>
  <Popup v-model:show="isShow" class="login-box" @closed="emit('closed')">
    <Tabs v-model:active="active" class="tabs" animated>
      <Tab>
        <template #title>
          <div class="tab-title">
            <t-svg name="yonghu" class="title-icon"></t-svg>
            <span>{{ $t("邮箱") }}</span>
          </div>
        </template>
        <el-form :model="formEmail" :rules="rules" ref="formEmailRef">
          <el-form-item prop="mail">
            <el-input size="large" v-model="formEmail.mail" :placeholder="$t('邮箱')">
              <template #prefix>
                <t-svg name="user" class="input-icon"></t-svg>
              </template>
            </el-input>
          </el-form-item>

          <el-form-item prop="mail_code">
            <el-input size="large" v-model="formEmail.mail_code" :placeholder="$t('验证码')">
              <template #prefix>
                <t-svg name="key" class="input-icon"></t-svg>
              </template>
              <template #suffix>
                <div class="send-code">
                  <SendCode :email="formEmail.mail" scene="register" :beforSend="beforSendEmail"></SendCode>
                </div>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item prop="password">
            <el-input size="large" v-model="formEmail.password" :placeholder="$t('密码')" type="password">
              <template #prefix>
                <t-svg name="pass" class="input-icon"></t-svg>
              </template>
            </el-input>
          </el-form-item>
          <PassLevel :REDACTED></PassLevel>
          <el-form-item prop="passwordAgain">
            <el-input size="large" v-model="formEmail.passwordAgain" :placeholder="$t('再次确认密码，与密码相同')" type="password">
              <template #prefix>
                <t-svg name="pass" class="input-icon"></t-svg>
              </template>
            </el-input>
          </el-form-item>
        </el-form>

      </Tab>
      <Tab>
        <template #title>
          <div class="tab-title">
            <t-svg name="dianhua" class="title-icon"></t-svg>
            <span>{{ $t("手机") }}</span>
          </div>
        </template>
        <el-form :model="formPhone" :rules="rules" ref="formPhoneRef">
          <el-form-item prop="phone">
            <el-input size="large" v-model="formPhone.phone" :placeholder="$t('输入手机号码')" inputmode="tel" type="tel">
              <template #prefix>
                <el-select v-model="formPhone.qh" class="select">
                  <el-option :label="`+${item.label}`" :value="item.value" v-for="item in systemStore.systemData?.idc"
                    :key="item.value" />
                </el-select>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item prop="code">
            <el-input size="large" v-model="formPhone.code" :placeholder="$t('验证码')">
              <template #prefix>
                <t-svg name="key" class="input-icon"></t-svg>
              </template>
              <template #suffix>
                <div class="send-code">
                  <SendCode :qh="formPhone.qh" :phone="formPhone.phone" scene="register" :beforSend="beforSendPhone">
                  </SendCode>
                </div>
              </template>
            </el-input>
          </el-form-item>
          <el-form-item prop="password">
            <el-input size="large" v-model="formPhone.password" :placeholder="$t('密码')" type="password">
              <template #prefix>
                <t-svg name="pass" class="input-icon"></t-svg>
              </template>
            </el-input>
          </el-form-item>
          <PassLevel :REDACTED></PassLevel>
          <el-form-item prop="passwordAgain">
            <el-input size="large" v-model="formPhone.passwordAgain" :placeholder="$t('再次确认密码，与密码相同')" type="password">
              <template #prefix>
                <t-svg name="pass" class="input-icon"></t-svg>
              </template>
            </el-input>
          </el-form-item>

        </el-form>
      </Tab>
    </Tabs>
    <el-button type="primary" size="large" class="full login-btn" @click="register" :loading="bthLoading">{{ $t("提交")
      }}</el-button>

  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref } from "vue";
import Popup from "@/components/common/Popup.vue";
import PassLevel from "./PassLevel.vue";
import { Tab, Tabs } from "vant";
import useStore from "@/store";
import SendCode from "./SendCode.vue";
import { passwordAgainRule, passwordRule, phoneCodeRule, phoneRule, usernameRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import lang from "@/lang";
import { ResCode } from "@/enum/ResultCode";
import { forget_mail_api, forget_phone_pwd } from "@/api/user";
import { openLink } from "@/utils/openLink";
const isShow = ref(false);
const active = ref(0);
const { systemStore } = useStore();
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});
const formEmailRef: Ref<FormInstance | null> = ref(null);
const formPhoneRef: Ref<FormInstance | null> = ref(null);


const formEmail = ref({
  mail: "",
  password: "",
  passwordAgain: "",
  mail_code: "",
});

const formPhone = ref({
  password: "",
  passwordAgain: "",
  qh: systemStore.systemData?.idc_default || "",
  phone: "",
  code: "",
});

// 表单规则
const rules = {
  mail: usernameRule(),
  password: passwordRule(),
  passwordAgain: passwordAgainRule(() => active.value == 0 ? formEmail.value.password : formPhone.value.password),
  phoneUsername: phoneRule(),
  phone: phoneRule(),
  code: phoneCodeRule(),
  mail_code: phoneCodeRule()
};

// 发送验证码前的验证
const beforSendPhone = async () => Boolean(await formPhoneRef.value?.validateField(["phone"]));
const beforSendEmail = async () => Boolean(await formEmailRef.value?.validateField(["mail"]));



// 提交
const bthLoading = ref(false);
const register = async () => {
  // 验证数据
  const validate = await (active.value == 0 ? formEmailRef : formPhoneRef).value?.validateField();
  if (!validate) return;


  bthLoading.value = true;

  const res = await (active.value === 0 ? forget_mail_api(formEmail.value) : forget_phone_pwd(formPhone.value));
  bthLoading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      message: lang.t("提交成功，请重新登录"),
      type: "success",
    });
    isShow.value = false;
    openLink("/login");
  }
};

</script>

<style scoped lang="less" src="@/assets/css/components/retrievePassword.less"></style>
