<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("订金") }}</div>
    <div class="balance-box">
      <div class="balance-warp">
        <span>{{ $t("账户余额") }}</span>
        <span class="balance">0.00</span>
        <t-svg name="shuaxin" class="refresh-icon"></t-svg>
      </div>
      <div class="settlement-period">
        <span class="settlement-text">{{ $t("沉降周期") }}</span>
        <span style="color: var(--color7);">{{ $t("1小时") }}</span>
      </div>
    </div>
    <div class="desc">{{ $t("进入和轮换不会产生审核。仅对收到的利息进行审计") }}</div>
    <div class="transfer-amount-box">
      <div class="transfer-amount-title">
        <span style="color: var(--color7);">{{ $t("价值转移") }}</span>
        <span>{{ $t("当前时间 22/04 23:05:26") }}</span>
      </div>
    </div>
    <el-form>
      <el-form-item>
        <el-input :placeholder="$t('Min10,Max6000')" size="large">
          <template #prefix>
            <span style="color: var(--color7);">R$</span>
          </template>
          <template #suffix>
            <span class="all">{{ $t("全部") }}</span>
          </template>
        </el-input>
      </el-form-item>
      <div class="tip">{{ $t("本次存款后，第一笔利息将于：24/05 18:11:00") }}</div>
      <el-button class="full" type="primary" size="large" @click="isShow = false">{{ $t('从转移') }}</el-button>

    </el-form>
  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
const isShow = ref(false);
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});
</script>

<style scoped lang="less" src="@/assets/css/components/interestDeposit.less"></style>