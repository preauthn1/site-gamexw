<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("验证码") }}</div>
    <el-form :model="form" :rules="rules" ref="formRef">
      <el-form-item v-if="userStore.userInfo?.yphone">
        <el-input size="large" :placeholder="$t('输入手机号码')" :value="userStore.userInfo?.yphone" disabled>
          <template #prefix>
            <el-select class="select" disabled :placeholder="`+${userStore.userInfo?.qh}`">
            </el-select>
          </template>
        </el-input>
      </el-form-item>

      <el-form-item v-else>
        <el-input size="large" :placeholder="$t('邮箱')" :value="userStore.userInfo?.mail" disabled>
          <template #prefix>
            <t-svg name="youxiang" class="input-icon"></t-svg>
          </template>
        </el-input>
      </el-form-item>


      <el-form-item prop="code">
        <el-input size="large" v-model="form.code" :placeholder="$t('验证码')">
          <template #prefix>
            <t-svg name="key" class="input-icon"></t-svg>
          </template>
          <template #suffix>
            <SendCode :email="userStore.userInfo?.mail" :qh="userStore.userInfo?.qh" :phone="userStore.userInfo?.yphone"
              scene="verify" class="send-code"></SendCode>
          </template>
        </el-input>
      </el-form-item>

    </el-form>
    <el-button class="full btn" type="primary" size="large" @click="submit" :loading="btnLoading">{{ $t('提交')
      }}</el-button>
  </Popup>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { phoneCodeRule } from "@/utils/rule";
import { FormInstance } from "element-plus";
import { ref, onMounted, Ref } from "vue";
const { userStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);

const props = defineProps<{
  callback: (code: string) => Promise<boolean>
}>();

// 表单
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  code: ""
});
const rules = {
  code: phoneCodeRule()
};
const btnLoading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  btnLoading.value = true;
  const confirm = await props.callback(form.value.code);
  btnLoading.value = false;
  if (confirm) {
    isShow.value = false;
  }
};

onMounted(() => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/sendCodePop.less"></style>