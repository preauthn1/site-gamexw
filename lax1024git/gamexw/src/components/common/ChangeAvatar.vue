<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class=" warp">
      <div class="title">{{ $t("更换头像") }}</div>
      <!-- <div class="tab-btn-box">
        <el-button type="primary" :plain="true" class="tab-btn">
          <t-svg name="nan" class="tab-icon"></t-svg>
          <span>{{ $t("男性") }}</span>
        </el-button>
        <el-button type="primary" :plain="false" class="tab-btn">
          <t-svg name="nv" class="tab-icon"></t-svg>
          <span>{{ $t("女性") }}</span>
        </el-button>
      </div> -->
      <el-skeleton animated :loading="loading">
        <template #template>
          <div class="avatar-box">
            <AspectRatio aspect="1/1" v-for="i in 25" :key="i">
              <el-skeleton-item variant="image" class="avatar" />
            </AspectRatio>
          </div>
        </template>
        <div class="avatar-box">
          <AspectRatio aspect="1/1" v-for="item, i in avatarList" :key="item.id">
            <t-img :src="item.url" class="avatar" :class="{ active: i === active }" @click="active = i" />
          </AspectRatio>
        </div>
      </el-skeleton>
      <el-button class="full btn" type="primary" size="large" @click="submit" :loading="btnLoading">{{ $t('保存')
        }}</el-button>
    </div>
  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref } from "vue";
import AspectRatio from "./AspectRatio.vue";
import { my_editinfo_api, user_get_default_head_img_api } from "@/api/user";
import { ResCode } from "@/enum/ResultCode";
import { AvatarListItem } from "@/types/api/user";
import useStore from "@/store";
import { ElMessage } from "element-plus";
import lang from "@/lang";
const { userStore } = useStore();
const isShow = ref(false);


// 获取列表
const active: Ref<number | null> = ref(null);
const avatarList: Ref<AvatarListItem[]> = ref([]);
const loading = ref(false);
const getData = async () => {
  loading.value = true;
  const res = await user_get_default_head_img_api();
  loading.value = false;
  if (res.code === ResCode.success) {
    avatarList.value = res.data;
    active.value = res.data.findIndex(item => item.url === userStore.userInfo?.head_img);
  }
};


const btnLoading = ref(false);
const submit = async () => {
  if (active.value == null) return;
  btnLoading.value = true;
  const res = await my_editinfo_api({ head_img: avatarList.value[active.value].url });
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      message: lang.t("更改成功"),
      type: "success"
    });
    isShow.value = false;
    userStore.getUserInfo();
  }
};

const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
  getData();
});
</script>

<style scoped lang="less" src="@/assets/css/components/changeAvatar.less"></style>