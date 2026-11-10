<template>
  <div class="page-box">
    <NavBar :title="$t('链接第三方登录')" class="nav-bar"></NavBar>
    <div class="content-box">
      <div class="title">
        <span>{{ $t("第三方链接") }}</span>
        <span>{{ $t("取消链接") }}</span>
      </div>
      <div class="desc">{{ $t("登录密码验证后可链接第三方") }}</div>
      <el-form label-position="top">
        <el-form-item>
          <template #label>
            <span class="label">{{ $t("检查登录密码") }}</span>
          </template>
          <el-input size="large" v-model="a" :placeholder="$t('输入密码')">
            <template #prefix>
              <t-svg name="pass" class="input-icon"></t-svg>
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
</script>

<style scoped lang="less" src="@/assets/css/pages/linkThirdLogin.less"></style>