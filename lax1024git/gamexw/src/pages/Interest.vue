<template>
  <div class="page-box">
    <NavBar :title="$t('费用')" class="nav-bar"></NavBar>
    <div class="card-box">
      <div class="top-card-box">
        <div class="left-tabel">
          <div class="left-tabel-item">
            <span>{{ $t("已存入") }}</span>
            <el-tooltip :visible="true" effect="customized" :content="$t('年利率15%')" placement="top-start" :offset="5">
              <span style="color: var(--color7);position: relative;">0.00</span>
            </el-tooltip>

          </div>
          <div class="left-tabel-item">
            <span>{{ $t("沉降周期") }}</span>
            <span style="color: var(--color7);"> {{ $t("1小时") }}</span>
          </div>
          <div class="left-tabel-item">
            <span>{{ $t("累计收藏") }}</span>
            <span>0.00</span>
          </div>
        </div>
        <div class="right-box">
          <div class="btn-list">
            <el-button type="primary" @click="$openLink('/interestDeposit')">{{ $t("订金") }}</el-button>
            <el-button type="primary" :disabled="true" @click="$openLink('/interestDeposit')">{{ $t("转移") }}</el-button>
          </div>
          <div class="claim-box">
            <div class="right-tabel">
              <div class="right-tabel-item">
                <span>{{ $t("最大利息") }}</span>
                <span style="color: var(--color7);">{{ $t("一个周期") }}</span>
              </div>
              <div class="right-tabel-item">
                <span>{{ $t("代办的") }}</span>
                <span style="color: var(--color6);">0.00</span>
              </div>
            </div>
            <el-button type="primary" :disabled="true">{{ $t("受到") }}</el-button>

          </div>
        </div>
      </div>


      <Tabs v-model:active="active" :shrink="true" class="tabs" animated>
        <Tab :title="$t('记录详情')">
          <div class="top-input-box">
            <el-select size="large" v-model="select" class="round select">
              <el-option label="巴西雷亚尔" value="1" />
              <el-option label="+65" value="2" />
              <el-option label="+75" value="3" />
            </el-select>
            <el-select size="large" v-model="select" class="round select">
              <el-option label="巴西雷亚尔" value="1" />
              <el-option label="+65" value="2" />
              <el-option label="+75" value="3" />
            </el-select>
            <div class="all-box">
              <span>{{ $t("待审核总额") }}</span>
              <span>R$0,00</span>
            </div>
          </div>
          <el-table :data="tableData" size="large" class="t-table">
            <el-table-column prop="bonus" :label="$t('时间')" />
            <el-table-column prop="bonus" :label="$t('类型')" />
            <el-table-column prop="bonus" :label="$t('数量')" />
          </el-table>
        </Tab>
        <Tab :title="$t('费用规则')">
          <div class="rule-box">
            {{ $t("费用规则详情") }}
          </div>
        </Tab>
      </Tabs>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tab, Tabs } from "vant";
import NavBar from "@/components/common/NavBar.vue";
import { ref } from "vue";
const active = ref(0);
const tableData = [
  { level: 0, bet: 1000, bonus: 1000, levelIcon: "vip0.png" },
];
const select = ref("1");

</script>

<style scoped lang="less" src="@/assets/css/pages/interest.less"></style>