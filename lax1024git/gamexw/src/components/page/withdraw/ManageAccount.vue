<template>
  <div class="manageAccount-box">
    <div class="title-box">
      <div class="title-left">
        <span>{{ $t("账户") }}</span>
        <span>({{ bankStore.bankList.length }})</span>
      </div>
      <div class="title-right" v-if="!isShowNum" @click="isShowNum = !isShowNum">
        <t-svg name="show"></t-svg>
        <span>{{ $t("显示") }}</span>
      </div>
      <div class="title-right" v-else @click="isShowNum = !isShowNum">
        <t-svg name="hide"></t-svg>
        <span>{{ $t("伪装") }}</span>
      </div>
    </div>
    <Empty class="empty" :loading="loading" v-if="bankStore.bankList.length === 0 && loading"></Empty>
    <div class="list" v-else>
      <div class="item-btn" v-for="item in bankStore.bankList" :key="item.id">
        <img :src="getBankTypeData(item.bank_type)?.icon" class="item-icon" />
        <div class="item-content">
          <div class="itme-name">
            <span style="color: var(--color7)">{{ getBankTypeData(item.bank_type)?.name }}</span>&nbsp;
            <span v-if="item.bank_type !== BankType.electronicWallet">({{ item.bank_name }})</span>
          </div>
          <div class="item-id">{{ isShowNum ? item.bank_number : bankNameFilter(item.bank_number) }}</div>
        </div>
        <t-svg name="xuanzhongduigou" class="tip-icon" size=""></t-svg>
      </div>
      <el-dropdown class="add-dropdown">
        <div class="add-box">
          <div class="item-btn">
            <t-svg name="addAcc" class="item-icon"></t-svg>
            <div class="item-content">
              <span style="color: var(--color7)">{{ $t("添加账户") }}</span>
            </div>
            <t-svg name="right"></t-svg>
          </div>
        </div>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item v-if="bankLists?.paytype.includes($t('PIX'))"
              @click="$openLink('/addPIX', { data: bankLists })">
              <div class="dropdown-item">{{ $t("PIX") }}</div>
            </el-dropdown-item>
            <el-dropdown-item v-if="bankLists?.paytype.includes($t('银行卡'))"
              @click="$openLink('/addBank', { data: bankLists })">
              <div class="dropdown-item">{{ $t("银行卡") }}</div>
            </el-dropdown-item>
            <el-dropdown-item v-if="bankLists?.paytype.includes($t('USDT'))"
              @click="$openLink('/addUsdt', { data: bankLists })">
              <div class="dropdown-item">{{ $t("USDT") }}</div>
            </el-dropdown-item>
            <el-dropdown-item v-if="bankLists?.paytype.includes($t('电子钱包'))"
              @click="$openLink('/addElectronicWallet', { data: bankLists })">
              <div class="dropdown-item">{{ $t("电子钱包") }}</div>
            </el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>

  </div>
</template>

<script setup lang="ts">
import { bank_lists_api } from "@/api/bank";
import { ResCode } from "@/enum/ResultCode";
import useStore from "@/store";
import { BankLists } from "@/types/api/bank";
import getBankTypeData from "@/utils/getBankTypeData";
import { Ref, onMounted, ref } from "vue";
import Empty from "@/components/common/Empty.vue";
import { BankType } from "@/enum/BankType";
import bankNameFilter from "@/utils/bankNameFilter";
const isShowNum = ref(false);

const { bankStore } = useStore();
const bankLists: Ref<BankLists | null> = ref(null);
const getBankLists = async () => {
  const res = await bank_lists_api();
  if (res.code === ResCode.success) {
    bankLists.value = res.data;
  }
};

const loading = ref(false);
onMounted(async () => {
  loading.value = true;
  await Promise.all([
    bankStore.getList(),
    getBankLists()
  ]);
  loading.value = false;
});
</script>

<style scoped lang="less" src="@/assets/css/pages/withdraw/manageAccount.less"></style>
