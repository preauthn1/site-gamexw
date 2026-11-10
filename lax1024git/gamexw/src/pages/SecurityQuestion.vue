<template>
  <div class="page-box">
    <NavBar :title="$t('安全问题')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="input-title">{{ $t("配置问题安全性") }}</div>
      <el-form>
        <el-form-item>
          <el-select size="large" v-model="select">
            <el-option label="巴西雷亚尔" value="1" />
            <el-option label="+65" value="2" />
            <el-option label="+75" value="3" />
            <template #prefix>
              <t-svg name="anquan" class="input-icon"></t-svg>
            </template>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-input size="large" v-model="a" :placeholder="$t('输入答案')">
            <template #prefix>
              <t-svg name="key" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-input size="large" v-model="a" :placeholder="$t('再次输入安全问题的答案')">
            <template #prefix>
              <t-svg name="key" class="input-icon"></t-svg>
            </template>
          </el-input>
        </el-form-item>
        <el-button type="primary" size="large" class="full btn">{{ $t("下一步") }}</el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { ref } from "vue";
const a = ref("");
const select = ref("1");
</script>

<style scoped lang="less" src="@/assets/css/pages/phone.less"></style>