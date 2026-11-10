<template>
    <Popup v-model:show="isShow" class="login-box" @closed="emit('closed')">
      <Tabs v-model:active="active" class="tabs" animated>
        <Tab>
          <template #title>
            <div class="tab-title" >
              <t-svg name="yonghu" class="title-icon"></t-svg>
              <span>{{ $t("邮箱注册") }}</span>
            </div>
          </template> 
          <el-form :model="formEmail" :rules="rules" ref="formEmailRef">

            <el-form-item prop="username">
              <el-input size="large" v-model="formEmail.username" :placeholder="$t('邮箱')">
                <template #prefix>
                  <t-svg name="user" class="input-icon"></t-svg>
                </template>
              </el-input>
            </el-form-item>

            <el-form-item prop="code" required v-if="systemStore.systemData?.data.phone_check == OpenState.open">
              <el-input size="large" v-model="formEmail.code" :placeholder="$t('验证码')">
                <template #prefix>
                  <t-svg name="key" class="input-icon"></t-svg>
                </template>
                <template #suffix>
                  <div class="send-code">
                    <SendCode :email="formEmail.username" scene="register" :beforSend="beforSendEmail"></SendCode>
                  </div>
                </template>
              </el-input>
            </el-form-item>
            <template v-else>
              <el-form-item prop="password">
                <el-input size="large" v-model="formEmail.password" :placeholder="$t('密码')" type="password">
                  <template #prefix>
                    <t-svg name="pass" class="input-icon"></t-svg>
                  </template>
                </el-input>
              </el-form-item>
              <PassLevel :REDACTED></PassLevel>
              <el-form-item prop="passwordAgain">
                <el-input size="large" v-model="formEmail.passwordAgain" :placeholder="$t('再次确认密码，与密码相同')"
                  type="password">
                  <template #prefix>
                    <t-svg name="pass" class="input-icon"></t-svg>
                  </template>
                </el-input>
              </el-form-item>
            </template>
            <el-form-item prop="phone">
              <el-input size="large" v-model="formEmail.phone" :placeholder="$t('输入手机号码')" inputmode="tel" type="tel">
                <template #prefix>
                  <el-select v-model="formEmail.qh" class="select">
                    <el-option :label="`+${item.label}`" :value="item.value" v-for="item in systemStore.systemData?.idc"
                      :key="item.value" />
                  </el-select>
                </template>
              </el-input>
            </el-form-item>

            <el-form-item prop="rec_code">
              <el-input size="large" v-model="formEmail.rec_code" :placeholder="$t('推广码')"
                :disabled="Boolean(shareCodeStore.code)" v-show="!shareCodeStore.code">
                <template #prefix>
                  <t-svg name="link" class="input-icon"></t-svg>
                </template>
              </el-input>
            </el-form-item>

          </el-form>

        </Tab>
        <Tab>
          <template #title>
            <div class="tab-title">
              <t-svg name="dianhua" class="title-icon"></t-svg>
              <span>{{ $t("手机注册") }}</span>
            </div>
          </template>
          <div class="desc">{{ $t("此字段仅用于注册或登录，不可用于链接！") }}</div>
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
            <el-form-item prop="code" v-if="systemStore.systemData?.data.phone_check == OpenState.open">
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
            <template v-else>
              <el-form-item prop="password">
                <el-input size="large" v-model="formPhone.password" :placeholder="$t('密码')" type="password">
                  <template #prefix>
                    <t-svg name="pass" class="input-icon"></t-svg>
                  </template>
                </el-input>
              </el-form-item>
              <PassLevel :REDACTED></PassLevel>
              <el-form-item prop="passwordAgain">
                <el-input size="large" v-model="formPhone.passwordAgain" :placeholder="$t('再次确认密码，与密码相同')"
                  type="password">
                  <template #prefix>
                    <t-svg name="pass" class="input-icon"></t-svg>
                  </template>
                </el-input>
              </el-form-item>
              <el-form-item prop="rec_code">
                <el-input size="large" v-model="formPhone.rec_code" :placeholder="$t('推广码')"
                  :disabled="Boolean(shareCodeStore.code)" v-show="!shareCodeStore.code">
                  <template #prefix>
                    <t-svg name="link" class="input-icon"></t-svg>
                  </template>
                </el-input>
              </el-form-item>
            </template>

          </el-form>
        </Tab>
      </Tabs>
      <el-checkbox size="large" v-model="checkbox18YearsOld" class="checkbox">
        <span>{{ $t("我已年满18岁，我已阅读并同意") }}</span>
        <span class="user-rule" @click.stop.prevent="$openLink('/rule', { type: 5 })">{{ $t("《用户协议》") }}</span>
      </el-checkbox>
      <el-button type="primary" size="large" class="full login-btn" @click="register" :loading="bthLoading">{{ $t("注册")
        }}</el-button>
      <div class="link-list">
        <div class="link" @click="$router.push('/msgCenter?current=0'); isShow = false">{{ $t("客户服务") }}</div>
        <div class="link" @click="$openLink('/login'); isShow = false">{{ $t("现在登录") }}</div>
      </div>
      <!-- <div class="other-title">
      <span>{{ $t("其他登录方式") }}</span>
    </div>
    <div class="other-box">
      <img :src="$require('google.png')" alt="" class="other-login-item">
      <img :src="$require('facebook.png')" alt="" class="other-login-item">
    </div> -->
    </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref, watch } from "vue";
import Popup from "@/components/common/Popup.vue";
import PassLevel from "./PassLevel.vue";
import { Tab, Tabs } from "vant";
import useStore from "@/store";
import SendCode from "./SendCode.vue";
import { passwordAgainRule, passwordRule, phoneCodeRule, phoneRule, usernameRule } from "@/utils/rule";
import { ElMessage, FormInstance } from "element-plus";
import lang from "@/lang";
import { login_register_api } from "@/api";
import { ResCode } from "@/enum/ResultCode";
import Token from "@/storage/Token";
import { useRouter } from "vue-router";
import { AccountType } from "@/enum/AccountType";
import { OpenState } from "@/enum/OpenState";
import { pixel } from "@/utils/pixel/createPixel";
import BaseStorage from "@/storage/BaseStorage";
const isShow = ref(false);
const active = ref(0);
const { systemStore, shareCodeStore, envStore } = useStore();
const $router = useRouter();
const pixelAcidStorage = new BaseStorage<string>("pixelAcid");

const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});
const formEmailRef: Ref<FormInstance | null> = ref(null);
const formPhoneRef: Ref<FormInstance | null> = ref(null);


const formEmail = ref({
  username: "",
  password: "",
  passwordAgain: "",
  qh: systemStore.systemData?.idc_default || "",
  phone: "",
  code: "",
  account_type: AccountType.email,
  rec_code: ""
});

const formPhone = ref({
  username: "",
  password: "",
  passwordAgain: "",
  qh: systemStore.systemData?.idc_default || "",
  phone: "",
  code: "",
  account_type: AccountType.phone,
  rec_code: ""
});
// 复制邀请码
watch(() => shareCodeStore.code, v => {
  formEmail.value.rec_code = v;
  formPhone.value.rec_code = v;
}, { immediate: true });

// 表单规则
const rules = {
  username: usernameRule(),
  password: passwordRule(),
  passwordAgain: passwordAgainRule(() => active.value == 0 ? formEmail.value.password : formPhone.value.password),
  phoneUsername: phoneRule(),
  phone: phoneRule(),
  code: phoneCodeRule()
};

// 发送验证码前的验证
const beforSendPhone = async () => Boolean(await formPhoneRef.value?.validateField(["phone"]));
const beforSendEmail = async () => Boolean(await formEmailRef.value?.validateField(["username"]));

const checkbox18YearsOld = ref(true);

// 执行注册操作
const bthLoading = ref(false);
const register = async () => {
  // 验证数据
  const validate = await (active.value == 0 ? formEmailRef : formPhoneRef).value?.validateField();
  if (!validate) return;
  if (!checkbox18YearsOld.value) {
    ElMessage({
      message: lang.t("首先查看并同意用户协议"),
      type: "error",
    });
    return;
  }

  bthLoading.value = true;
  // 手机号码注册，表单用户名取电话号码
  formPhone.value.username = formPhone.value.phone;
  const res = await login_register_api({
    ...(active.value === 0 ? formEmail.value : formPhone.value),
    device_type: envStore.getDevice(),
    acid: pixelAcidStorage.getData()
  });
  bthLoading.value = false;
  if (res.code === ResCode.success) {
    Token.setData(res.data);
    isShow.value = false;
    pixel.value?.register();
    $router.push("/");
    ElMessage({
      message: lang.t("注册成功"),
      type: "success",
    });
  }
};

</script>

<style scoped lang="less" src="@/assets/css/components/register.less">
</style>
