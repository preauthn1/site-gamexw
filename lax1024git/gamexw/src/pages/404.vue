<template>
  <div class="box">
    <t-svg name="404" class="cover"></t-svg>
    <el-button type="primary" size="large" @click="$router.replace('/')">{{ $t("返回首页") }}</el-button>
  </div>
</template>

<script setup lang="ts">

</script>

<style scoped lang="less" src="@/assets/css/pages/404.less"></style>