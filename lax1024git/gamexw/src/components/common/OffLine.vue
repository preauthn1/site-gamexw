<template>
  <Popup v-model:show="isShow" @closed="emit('closed')" :position="systemStore.isPhone ? 'bottom' : 'center'">
    <div class="title">
      <t-svg name="left" class="left-icon pc-hide" @click="isShow = false"></t-svg>
      <span>{{ $t("线下充值") }}</span>
    </div>


    <el-form label-position="top">
      <div class="content">
        <el-form-item :label="$t('名称')">
          <el-input :value="poprs.data.bank_name" readonly size="large">
            <template #suffix>
              <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.bank_name)"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('账户地址')">
          <el-input :value="poprs.data.bank_number" readonly size="large">
            <template #suffix>
              <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.bank_number)"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('户名')" v-if="poprs.data.bank_khm">
          <el-input :value="poprs.data.bank_khm" readonly size="large">
            <template #suffix>
              <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.bank_khm)"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('开户行')" v-if="poprs.data.bank_open">
          <el-input :value="poprs.data.bank_open" readonly size="large">
            <template #suffix>
              <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.bank_open)"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('付款金额')">
          <el-input :value="$numInit(poprs.data.apply_price)" readonly size="large">
            <template #prefix>
              <div class="input-icon">{{ poprs.data.currencyApi.currency_symbol }}</div>
            </template>
            <template #suffix>
              <t-svg name="copy" class="copy-icon" @click="$copy(poprs.data.apply_price)"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('到账金额')">
          <el-input :value="$numInit(poprs.data.computedProce)" readonly size="large">
            <template #prefix>
              <div class="input-icon">{{ poprs.data.currency_symbol }}</div>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item :label="$t('支付凭证')">
          <Upload v-model="form.credentials"></Upload>
        </el-form-item>
      </div>

      <el-button class="full btn" type="primary" size="large" @click="submit" :loading="loading">{{ $t("提交")
        }}</el-button>
    </el-form>

  </Popup>
</template>

<script setup lang="ts">
import useStore from "@/store";
import { onMounted, ref } from "vue";
import Upload from "@/components/common/Upload.vue";
import { RechargeAll } from "@/types/api/recharge";
import { ElementType } from "@/types/utils";
import { ElMessage } from "element-plus";
import lang from "@/lang";
const { systemStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);
const poprs = defineProps<{
  data: ElementType<RechargeAll["offline"]> & { apply_price: string, currency_symbol: string, computedProce: number },
  callBack: (credentials: string) => boolean
}>();

const form = ref({
  credentials: ""
});

const loading = ref(false);
const submit = async () => {
  if (!form.value.credentials) {
    ElMessage({
      type: "error",
      message: lang.t("请上传支付凭证")
    });
    return;
  }
  loading.value = true;
  console.log(poprs);
  const confirm = await poprs.callBack(form.value.credentials);
  loading.value = false;
  if (confirm) isShow.value = false;
};

onMounted(() => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/offline.less"></style>