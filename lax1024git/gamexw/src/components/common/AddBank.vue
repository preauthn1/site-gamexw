<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("添加银行卡") }}</div>
    <el-form label-position="top" :model="form" :rules="rules" ref="formRef">
      <el-form-item :label="$t('户名')" prop="bank_hm">
        <el-input size="large" v-model="form.bank_hm" :placeholder="$t('请输入开户名')"></el-input>
      </el-form-item>
      <el-form-item :label="$t('银行名称')" prop="bank_name">
        <el-select size="large" v-model="form.bank_name" :placeholder="$t('请选择银行')">
          <el-option :value="item.bank_name" v-for="item in poprs.data?.bank_list[0]" :key="item.bank_name" />
        </el-select>
      </el-form-item>
      <el-form-item :label="$t('银行账户')" prop="bank_number">
        <el-input size="large" v-model="form.bank_number" :placeholder="$t('请输入银行账户')"></el-input>
      </el-form-item>
      <el-form-item :label="$t('开户行')" prop="bank_open">
        <el-input size="large" v-model="form.bank_open" :placeholder="$t('请输入开户行')"></el-input>
      </el-form-item>


      <el-button class="full btn" type="primary" size="large" @click="submit" :loading="loading">{{ $t("添加")
        }}</el-button>
    </el-form>

  </Popup>
</template>

<script setup lang="ts">
import { Ref, onMounted, ref } from "vue";
import { BankLists } from "@/types/api/bank";
import { ElMessage, FormInstance } from "element-plus";
import lang from "@/lang";
import { BankType } from "@/enum/BankType";
import { bank_add_api } from "@/api/bank";
import { ResCode } from "@/enum/ResultCode";
import useStore from "@/store";
const { bankStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);
const poprs = defineProps<{
  data?: BankLists,
  onSuccess?: () => void
}>();

const formRef: Ref<FormInstance | null> = ref(null);

const form = ref({
  bank_hm: "",
  bank_name: "",
  bank_number: "",
  bank_open: "",
  bank_type: BankType.bank
});
const rules = {
  bank_hm: [
    {
      required: true,
      message: lang.t("请输入开户名"),
      trigger: "change",
    },
  ],
  bank_name: [
    {
      required: true,
      message: lang.t("请选择银行"),
      trigger: "change",
    },
  ],
  bank_number: [
    {
      required: true,
      message: lang.t("请输入银行账户"),
      trigger: "change",
    },
  ],
  bank_open: [
    {
      required: true,
      message: lang.t("请输入开户行"),
      trigger: "change",
    },
  ],

};

const loading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
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