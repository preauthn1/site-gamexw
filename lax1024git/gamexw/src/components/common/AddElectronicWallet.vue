<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("添加电子钱包") }}</div>
    <el-form label-position="top" :model="form" :rules="rules" ref="formRef">
      <el-form-item :label="$t('开户名')" prop="bank_hm">
        <el-input size="large" v-model="form.bank_hm" :placeholder="$t('请输入开户名')"></el-input>
      </el-form-item>
      <el-form-item :label="$t('钱包号码')" prop="bank_number">
        <el-input size="large" v-model="form.bank_number" :placeholder="$t('请输入钱包号码')"></el-input>
      </el-form-item>



      <el-button class="full btn" type="primary" size="large" @click="submit" :loading="loading">{{ $t("添加")
        }}</el-button>
    </el-form>

  </Popup>
</template>

<script setup lang="ts">
import { Ref, onMounted, ref } from "vue";
import { ElMessage, FormInstance } from "element-plus";
import lang from "@/lang";
import { BankType } from "@/enum/BankType";
import { bank_add_api } from "@/api/bank";
import { ResCode } from "@/enum/ResultCode";
import useStore from "@/store";
const { bankStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);


const formRef: Ref<FormInstance | null> = ref(null);

const form = ref({
  bank_hm: "",
  bank_name: "",
  bank_number: "",
  bank_type: BankType.electronicWallet
});
const rules = {
  bank_hm: [
    {
      required: true,
      message: lang.t("请输入开户名"),
      trigger: "change",
    },
  ],
  bank_number: [
    {
      required: true,
      message: lang.t("请输入钱包号码"),
      trigger: "change",
    },
  ]
};

const loading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  form.value.bank_name = form.value.bank_hm;
  loading.value = true;
  const res = await bank_add_api(form.value);
  loading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: lang.t("添加成功")
    });
    isShow.value = false;
    bankStore.getList(false);
  }
};

onMounted(() => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/addAcc.less"></style>