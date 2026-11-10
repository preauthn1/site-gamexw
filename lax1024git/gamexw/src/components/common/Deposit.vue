<template>
  <Popup v-model:show="isShow" @closed="emit('closed')" :position="systemStore.isPhone ? 'bottom' : 'center'">
    <div class="title">
      <t-svg name="left" class="left-icon pc-hide" @click="isShow = false"></t-svg>
      <span>{{ $t("充值") }}</span>
      <div class="title-right" @click="$openLink('/depositRecords')">{{ $t("充值记录") }}</div>
    </div>
    <Tabs v-model:active="tabActive" :shrink="true">
      <Tab v-for="tabItem in tabList" :key="tabItem.type">
        <template #title>
          <div class="tab-title">
            <t-svg :name="tabItem.icon" class="tabs-icon"></t-svg>
            <div>{{ $t(tabItem.name) }}</div>
          </div>
        </template>
        <el-form :rules="rules" ref="formRef" :model="form">
          <div class="select-box1">
            <div class="select-btn" :class="{ active: rechargeTypeActive == index }"
              v-for="item, index in rechargeTypeList" :key="item.type" @click="rechargeTypeActive = index">
              <img :src="item.icon" class="select-btn-icon">
              <span>{{ $t(item.name) }}</span>
              <t-svg class="active-icon" name="xuanzhongduigou" size=""></t-svg>
            </div>
          </div>
          <div class="select-box2">
            <div class="select-btn" :class="{ active: index === channeActive }" v-for="item, index in channelList"
              :key="item.id" @click="channeActive = index">
              <span>{{ item['bank_name'] || item['pay_name'] }}</span>
              <t-svg class="active-icon" name="xuanzhongduigou" size=""></t-svg>
            </div>
          </div>
          <div class="amount-title">{{ $t("充值金额") }}</div>
          <div class="select-box3">
            <div class="select-btn" :class="{ active: form.apply_price === item }" v-for="item, index in amountList"
              :key="index" @click="form.apply_price = item">
              <span>{{ item }}</span>
              <t-svg class="active-icon" name="xuanzhongduigou" size=""></t-svg>
              <div class="bonus-corner-marker" v-if="computedBonus(Number(item))">+{{
                computedBonus(Number(item))?.bonus || 0
              }}</div>
            </div>
          </div>
          <el-form-item prop="apply_price">
            <el-input class="input" :placeholder="placeholderText()" size="large" v-model="form.apply_price"
              type="number">
              <template #prefix>
                <div class="input-icon">{{ rechargeAll?.currency_symbol }}</div>
              </template>
              <template #suffix v-if="computedBonus(Number(form.apply_price))">
                <div class="input-bonus">+{{
                  computedBonus(Number(form.apply_price))?.bonus
                }}</div>
              </template>
            </el-input>
          </el-form-item>

          <el-form-item v-if="isAction">
            <el-select size="large" v-model="form.not_auto_action" :placeholder="$t('请选择参与活动')">
              <el-option :label="item.news_title" :value="item.id" v-for="item in rechargeAll?.action_list"
                :key="item.id" />
            </el-select>
          </el-form-item>

          <!-- 单位不一样，进行实时汇率显示 -->
          <div class="tips" v-if="channeActiveData?.currencyApi.currency_symbol !== rechargeAll?.currency_symbol">
            <div class="tip">
              {{ $t("实时汇率") }}
              <span style="color: var(--color4);">
                {{ channeActiveData?.currencyApi.currency_symbol }} 1 =
                {{ rechargeAll?.currency_symbol }} {{ Number(channeActiveData?.currencyApi.recharge_rate) }}
              </span>
            </div>
            <div class="tip">
              {{ $t("实际付款金额") }}
              <span style="color: var(--color4);">
                {{ channeActiveData?.currencyApi.currency_symbol }}
                {{ $numInit(currencyConversion(Number(form.apply_price), true)) }}
              </span>
            </div>
            <div class="tip">
              {{ $t("实际到账金额") }}
              <span style="color: var(--color4);">
                {{ rechargeAll?.currency_symbol }}
                {{ $numInit(Number(form.apply_price) + (computedBonus(Number(form.apply_price))?.bonus || 0)) }}
              </span>
            </div>
          </div>

          <el-button class="full btn" type="primary" size="large" @click="submit()" :loading="btnLoading">{{ $t("现在充值")
            }}</el-button>
        </el-form>
      </Tab>
    </Tabs>

  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref, computed } from "vue";
import { Tab, Tabs } from "vant";
import { RechargeAll } from "@/types/api/recharge";
import useStore from "@/store";
import { recharge_apply_api, recharge_get_all_api } from "@/api/recharge";
import { ResCode } from "@/enum/ResultCode";
import { RechargeType } from "@/enum/RechargeType";
import { require } from "@/utils/$require";
import { watch } from "vue";
import lang from "@/lang";
import { ElMessage, FormInstance } from "element-plus";
import { openLink } from "@/utils/openLink";
import Loading from "@/utils/loading";
import ApiStorage from "@/storage/ApiStorage";

const { systemStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);
const formRef: Ref<FormInstance[] | null> = ref(null);

// 0是在线充值
// 1是线下充值
const tabList = computed(()=>{
  const list:{name:string,type:string,icon:string}[] = [];
  if(rechargeAll.value?.online.length) list.push({ name: "网上存款", type: "online", icon: "online" });
  if(rechargeAll.value?.offline.length) list.push({ name: "线下存款", type: "offline", icon: "offline" });
  return list;
});
const tabActive = ref(0);//选中的索引
const tabActiveData = computed(() => tabList.value[tabActive.value]);//选中的索引


// 充值类型选择 电子钱包 pix usdt相关钱包分类
const rechargeTypeList = computed(() => {
  const rechargeTypeList = [
    {
      name: "银行卡",
      icon: require("bank.png"),
      type: RechargeType.bank
    },
    {
      name: "USDT",
      icon: require("usdt.png"),
      type: RechargeType.usdt
    },
    {
      name: "电子钱包",
      icon: require("electronicWallet.png"),
      type: RechargeType.electronicWallet
    },
    {
      name: "PIX",
      icon: require("pix.png"),
      type: RechargeType.pix
    },
  ];
  // 筛选掉下面不存在的渠道,列表内不显示
  const typeList = [
    ...(
      (tabActiveData.value.type === "online" ? rechargeAll.value?.online : rechargeAll.value?.offline)
      || []
    )].map(item => item.type);
  return rechargeTypeList.filter(item => typeList.includes(item.type));
});
const rechargeTypeActive = ref(0); //选中的索引
const rechargeTypeActiveData = computed(() => rechargeTypeList.value[rechargeTypeActive.value]); //选中的数据


// 充值渠道列表
// 从充值类型里面选择需要展示的充值渠道列表
const channelList = computed(() => {
  const channelList = (tabActiveData.value.type === "online" ? rechargeAll.value?.online : rechargeAll.value?.offline);
  return channelList?.filter(item => item.type === rechargeTypeActiveData.value.type) || [];
});
const channeActive = ref(0);
const channeActiveData = computed(() => channelList.value[channeActive.value]); //选中的数据

// 切换选项，重置下级选项，避免切换到不必要的索引导致的错误
watch(tabActive, () => {
  rechargeTypeActive.value = 0;
  channeActive.value = 0;
});
watch(rechargeTypeActive, () => {
  channeActive.value = 0;
});


// 是否参与活动
const isAction = ref(false);

// 获取数据
const rechargeAll: Ref<RechargeAll | null> = ref(null);
const getRechargeAll = async () => {
  const fetch = new ApiStorage({
    api: () => recharge_get_all_api(),
    success(data) {
      rechargeAll.value = data;
      if (rechargeAll.value.action_list.length && rechargeAll.value.action_is_force_join === 1) {
        isAction.value = true;
        form.value.not_auto_action = rechargeAll.value.action_list[0].id;
      }
    },
  });
  await fetch.getData();
};

// 表单相关
const form = ref({
  apply_price: "",
  credentials: "",
  not_auto_action: 0
});

// 表单规则
const rules = {
  apply_price: [
    {
      required: true, message: lang.t("请输入金额"), trigger: "change"
    },
    {
      validator: (_rule: unknown, value: string, callback: (err?: Error) => void) => {
        const valueNum = Number(value);
        if ((valueNum > max.value || valueNum < min.value) && max.value > 0) {
          callback(new Error(lang.t("最少{min}，最多{max}", { min: min.value, max: max.value })));
        }
        callback();
      },
      trigger: "change",
    },
  ]
};




// 提交表单
const btnLoading = ref(false);
const submit = async (credentials?: string) => {
  const validate = await formRef.value?.[tabActive.value].validate();
  if (!validate) return;
  // 线下充值上传支付凭证
  if (tabActiveData.value.type === "offline" && !credentials) {
    showOffLine();
    return;
  }
  btnLoading.value = true;
  const res = await recharge_apply_api({
    apply_price: currencyConversion(Number(form.value.apply_price), true),
    not_auto_action: form.value.not_auto_action,
    type: channeActiveData.value.pay_type,
    bank_sk_id: channeActiveData.value.id,
    credentials,
    usdt_type: channeActiveData.value?.["usdt_type"]
  });
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    // 跳转
    if (res.data.handle_type === "navto") {
      window.location.assign(res.data.pay_url);
    } else if (res.data.is_qrcode == 1) {
      // 显示二维码收款
      openLink("/qrCodePayment", {
        data: res.data
      });
    } else {
      ElMessage({
        type: "success",
        message: lang.t("提交成功，等待后台审核")
      });
    }
    isShow.value = false;
    return true;
  }
};

// 显示线下充值弹窗
const showOffLine = () => {
  openLink("/offLine", {
    data: {
      ...channeActiveData.value,
      apply_price: currencyConversion(Number(form.value.apply_price), true),
      computedProce: Number(form.value.apply_price) + (computedBonus(Number(form.value.apply_price))?.bonus || 0),
      currency_symbol: rechargeAll.value?.currency_symbol,
    },
    callBack: submit
  });
};


// 计算奖金
const computedBonus = (amount: number) => {
  const reg_json = form.value.not_auto_action ? rechargeAll.value?.action_list.find(item => item.id === form.value.not_auto_action)?.reg_json : rechargeAll.value?.recharge_action_reg_json;
  const activeBonusData = reg_json?.find(item => amount <= Number(item.max_reg) && amount >= Number(item.min_reg));
  if (activeBonusData) {
    return {
      ...activeBonusData,
      bonus: activeBonusData.type === "1" ? Number(activeBonusData.bl_or_amount) : amount * Number(activeBonusData.bl_or_amount) / 100
    };
  } else {
    return null;
  }
};

// 快捷输入
const amountList = computed(() => {
  const list = (rechargeAll.value?.recharge_amount_sitting.bank_recharge_list || []).filter(item => Number(item) >= min.value && Number(item) <= max.value);
  return list;
});


// 货币单位转换
const currencyConversion = (currency: number, isReverse: boolean = false) => {
  const currencyNew = isReverse ? (currency / Number(channeActiveData.value?.currencyApi.recharge_rate)).toFixed(2)
    : (currency * Number(channeActiveData.value?.currencyApi.recharge_rate)).toFixed(2);
  return Number(currencyNew);
};

// 最小金额
const min = computed(() => currencyConversion(Number(channeActiveData.value?.["pay_min_amout"] || channeActiveData.value?.["min_amcount"])));

// 最大金额
const max = computed(() => currencyConversion(Number(channeActiveData.value?.["pay_max_amout"] || channeActiveData.value?.["max_amcount"])));

// 占位文字，根据限制变化
const placeholderText = () => {
  if (max.value > 0) {
    return lang.t("最少{min}，最多{max}", { min: min.value, max: max.value });
  } else {
    return lang.t("请输入充值金额");
  }
};


onMounted(async () => {
  Loading.show();
  await getRechargeAll();
  Loading.hide();
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/deposit.less"></style>