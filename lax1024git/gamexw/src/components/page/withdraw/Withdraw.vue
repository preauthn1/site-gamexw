<template>
  <div class="page-wrap" v-loading="loading">
    <el-form label-position="top" :model="form" ref="formRef" :rules="rules">
      <div class="balance-box">
        <div class="balance-left">
          <div class="balance-warp">
            <span>{{ $t("余额") }}</span>
            <span>{{ $numInit(userStore.userInfo?.balance) }}</span>
            <Refresh :is-refresh="isRefresh" class="refresh-icon" @click="refreshBalance"></Refresh>
          </div>
          <div class="balance-desc" v-if="Number(userStore.userInfo?.savings) > 0">
            {{ $t("还需 {savings} 流水才能提款", { savings: $numInit(userStore.userInfo?.savings) }) }}
          </div>
        </div>
        <!-- <el-button type="primary" @click="$router.push('/interest')">{{ $t("费用") }}</el-button> -->
      </div>
      <div class="tip-btn">
        <span>{{ $t("正常提现") }}</span>
        <t-svg name="xuanzhongduigou" class="tip-icon" size=""></t-svg>
      </div>
      <el-form-item prop="bank_id">
        <el-input size="large" readonly :value="$t('新增账户')" class="add" @click="$router.replace('?current=2')"
          v-if="bankStore.bankList.length === 0">
          <template #prefix>
            <t-svg name="addAcc" class="input-icon"></t-svg>
          </template>
          <template #suffix>
            <t-svg name="right"></t-svg>
          </template>
        </el-input>
        <el-select v-else size="large" v-model="form.bank_id" :placeholder="$t('请选择账户')">
          <template #prefix v-if="activeBankData">
            <img :src="getBankTypeData(activeBankData.bank_type)?.icon" class="prefix-icon">
          </template>
          <el-option :value="item.id" :label="bankNameFilter(item.bank_number)" v-for="item in bankStore.bankList"
            :key="item.id">
            <div class="option-item">
              <img :src="getBankTypeData(item.bank_type)?.icon" class="prefix-icon">
              <span>{{ bankNameFilter(item.bank_number) }}</span>
            </div>
          </el-option>
        </el-select>
      </el-form-item>
      <el-form-item prop="w_price">
        <el-input size="large" :placeholder="placeholderPrice" v-model="form.w_price" type="number">
          <template #prefix>
            <span class="input-unit">{{ systemStore.systemData?.data.currency.symbol }}</span>
          </template>
        </el-input>
      </el-form-item>
      <div class="line"></div>

      <el-form-item :label="$t('支付密码')" v-if="limitData?.pay_passwd_verify === OpenState.open" prop="wpass">
        <PayPassWordInput v-model:value="form.wpass"></PayPassWordInput>
      </el-form-item>

      <el-button class="full btn" size="large" type="primary" @click="submit">{{ $t("确认") }}</el-button>
    </el-form>

  </div>
</template>

<script setup lang="ts">
import { Ref, computed, onMounted, ref } from "vue";
import PayPassWordInput from "@/components/common/PayPassWordInput.vue";
import useStore from "@/store";
import bankNameFilter from "@/utils/bankNameFilter";
import getBankTypeData from "@/utils/getBankTypeData";
import { withdraw_apply_api, withdraw_limit_api } from "@/api/withdraw";
import { ResCode } from "@/enum/ResultCode";
import { WithdrawLimit } from "@/types/api/withdraw";
import Refresh from "@/components/common/Refresh.vue";
import { openLink } from "@/utils/openLink";
import { OpenState } from "@/enum/OpenState";
import { payPasswordRule } from "@/utils/rule";
import lang from "@/lang";
import { ElMessage, FormInstance } from "element-plus";
import numInit from "@/utils/numInit";
import ApiStorage from "@/storage/ApiStorage";
const { bankStore, userStore, systemStore } = useStore();


// 刷新余额
const isRefresh = ref(false);
const refreshBalance = async () => {
  if (isRefresh.value) return;
  isRefresh.value = true;
  await userStore.getUserInfo();
  isRefresh.value = false;
};

// 提现限制数据
const limitData: Ref<WithdrawLimit | null> = ref(null);
const getLimit = async () => {
  await new ApiStorage({
    api: () => withdraw_limit_api(),
    success(data) {
      limitData.value = data;
    },
  }).getData();
};

// 选中的提现银行数据
const activeBankData = computed(() => {
  return bankStore.bankList.find(item => item.id === Number(form.value.bank_id));
});


// 表单
const formRef: Ref<FormInstance | null> = ref(null);
const form = ref({
  bank_id: "",
  w_price: "",
  first_withdrawal_code: "",
  phone: "",
  verify_code: "",
  wpass: ""
});

// 规则
const rules = {
  wpass: payPasswordRule(),
  bank_id: [
    {
      required: true,
      message: lang.t("请选择账户"),
      trigger: "change",
    },
  ],
  w_price: [
    {
      required: true,
      message: lang.t("请输入提现金额"),
      trigger: "change",
    },
    {
      validator: (_rule: unknown, value: string, callback: (err?: Error) => void) => {
        if (Number(value) < Number(limitData.value?.old_min_amount) || Number(value) > Number(limitData.value?.old_max_amount)) {
          callback(new Error(placeholderPrice.value));
        }
        callback();
      },
      trigger: "change",
    },
  ]
};

// 占位符提示文字
const placeholderPrice = computed(() => {
  if (!limitData.value) return "";
  return lang.t("最小{min}，最大{max}", { min: Number(limitData.value?.old_min_amount), max: Number(limitData.value?.old_max_amount) });
});


// 提交表单
const submit = async () => {

  const validate = await formRef.value?.validate();
  if (!validate) return;

  // 验证流水
  if (Number(userStore.userInfo?.savings) > 0) {
    ElMessage({
      type: "error",
      message: lang.t("还需 {savings} 流水才能提款", { savings: numInit(userStore.userInfo?.savings) })
    });
    return;
  }

  // 验证码开启，并且没有输入的时候，开始验证
  if (limitData.value?.pay_passwd_verify === OpenState.open && !form.value.first_withdrawal_code) {
    openCode("first_withdrawal_code");
    return;
  }

  const res = await withdraw_apply_api({
    ...form.value,
    phone: userStore.userInfo?.yphone,
  });
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: lang.t("提交成功")
    });
    getLimit();
    formRef.value?.resetFields();
    return true;
  } else if (res.code === ResCode.verificationCode) {
    openCode("verify_code");
  } else {
    ElMessage({
      type: "error",
      message: res.msg
    });
  }

};

// 显示验证码
const openCode = (type: "first_withdrawal_code" | "verify_code") => {
  openLink("/sendCodePop", {
    callback: async (code: string) => {
      form.value[type] = code;
      const flag = await submit();
      form.value[type] = "";
      return flag;
    }
  });
};

const loading = ref(false);
onMounted(async () => {
  loading.value = true;
  await Promise.all([
    bankStore.getList(),
    getLimit()
  ]);
  loading.value = false;
});
</script>

<style scoped lang="less" src="@/assets/css/pages/withdraw/withdraw.less"></style>