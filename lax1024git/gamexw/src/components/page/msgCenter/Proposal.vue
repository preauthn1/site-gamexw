<template>
  <div class="content-box">
    <div class="btn-box">
      <el-button type="primary" :plain="!(active === 0)" @click="active = 0" class="btn-tab">{{ $t("创建反馈")
        }}</el-button>
      <!-- <el-button type="primary" :plain="!(active === 1)" @click="active = 1" class="btn-tab">{{ $t("我的反馈")
        }}</el-button> -->
      <div class="top-data-box" v-if="active === 1">
        <span>{{ $t("代办的") }}</span>
        <span class="top-price">R$0,00</span>
        <el-button type="primary" :disabled="true">{{ $t("收集一切") }}</el-button>
      </div>
    </div>
    <!-- 创建反馈 -->
    <el-form label-position="top" v-if="active === 0" :rules="rules" :model="form" ref="formRef">
      <el-form-item prop="desc" class="input-top">
        <template #label>
          <span>{{ $t("反馈内容") }}</span>
          <span>（{{ $t("反馈问题我会跟进") }}）</span>
        </template>
        <el-input type="textarea" v-model="form.desc"
          :autosize="systemStore.isPhone ? { minRows: 6, maxRows: 6 } : { minRows: 9, maxRows: 9 }"
          :placeholder="$t('您的意见对我们很有价值。任何有价值的建议都会被考虑，一旦被采纳，重要程度将决定现金奖励。欢迎提出建议')"></el-input>
      </el-form-item>
      <el-form-item prop="img">
        <template #label>
          <span>{{ $t("图片不会说谎") }}</span>
          <span>（{{ $t("更容易采用") }}）</span>
        </template>
        <Upload v-model="form.img"></Upload>
      </el-form-item>
      <div class="upload-tip">{{ $t("支持发送图像和视频（最大50MB）") }}</div>
      <div class="rule-title">{{ $t("奖励规则") }}</div>
      <div class="rule">{{ $t("我们专门设立了丰厚的奖金来收集反馈，以优化系统和功能，为您提供更好的体验！一旦接受，将根据效用给与奖励（未接收的除外）。") }}</div>
      <el-button type="primary" size="large" class="btn" @click="submit" :loading="loading">{{ $t("发送反馈") }}</el-button>
    </el-form>
    <!-- 我的反馈 -->
    <Empty class="empty" v-else></Empty>
  </div>
</template>

<script setup lang="ts">
import Empty from "@/components/common/Empty.vue";
import { Ref, ref } from "vue";
import Upload from "@/components/common/Upload.vue";
import useStore from "@/store";
import { ElMessage, FormInstance } from "element-plus";
import lang from "@/lang";
const { systemStore } = useStore();
const active = ref(0);
const form = ref({
  desc: "",
  img: ""
});
const formRef: Ref<FormInstance | null> = ref(null);
const rules = {
  desc: [
    {
      required: true,
      message: lang.t("请输入反馈问题"),
      trigger: "change",
    },
  ]
};


const loading = ref(false);
const submit = async () => {
  const validate = await formRef.value?.validate();
  if (!validate) return;
  loading.value = true;
  setTimeout(() => {
    loading.value = false;
    formRef.value?.resetFields();
    ElMessage({
      type: "success",
      message: "感谢您的反馈，我们已接收，并进行持续跟进"
    });
  }, 500);
};
</script>

<style scoped lang="less" src="@/assets/css/pages/msgCenter/proposal.less"></style>