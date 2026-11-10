<template>
  <div class="avatar-uploader" @click="upload" v-loading="loading">
    <t-img v-if="props.modelValue" :src="props.modelValue" class="avatar" />
    <el-icon v-else class="avatar-uploader-icon">
      <Plus />
    </el-icon>
  </div>
</template>

<script setup lang="ts">
import { upload_api } from "@/api/upload";
import { ResCode } from "@/enum/ResultCode";
import chooseFile from "@/utils/chooseFile";
import { Plus } from "@element-plus/icons-vue";
import { ref } from "vue";
const props = defineProps({
  modelValue: String
});
const emits = defineEmits(["update:modelValue"]);
const loading = ref(false);
const upload = () => {
  chooseFile(async file => {
    loading.value = true;
    const res = await upload_api(file);
    loading.value = false;
    if (res.code === ResCode.success) {
      emits("update:modelValue", res.data.file);
    }
  });
};
</script>

<style scoped lang="less">
.el-icon.avatar-uploader-icon {
  font-size: 28px;
  color: var(--color2);
  width: 150px;
  height: 150px;
  text-align: center;
  border: 1px solid var(--color12);
  background-color: var(--color9);
  border-radius: var(--border-radius);
}

.avatar {
  width: 150px;
  height: 150px;
  background-color: var(--color9);
  object-fit: cover;
  border-radius: var(--border-radius);
}
</style>