<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="data?.c_title">
      <template #right>
        <div class="m-hide">
          <el-button type="primary" @click="$router.push('/msgCenter?current=0')"
            v-if="data?.base_type == 5 || data?.auto_apply == 1">{{ $t("联系客服") }}</el-button>
          <template v-else>
            <el-button type="primary" v-if="!isApplay" @click="apply" :loading="btnLoading">{{ $t("活动申请") }}</el-button>
            <el-button type="primary" v-else disabled>{{ $t("已申请") }}</el-button>
          </template>
        </div>
      </template>
    </NavBar>
    <div class="content html" v-html="data?.c_detail"></div>
    <AllActivity></AllActivity>
    <div class="foot pc-hide">
      <div class="foot-content">
        <el-button class="full" size="large" type="primary" @click="$router.push('/msgCenter?current=0')"
          v-if="data?.base_type == 5 || data?.auto_apply == 1">{{ $t("联系客服") }}</el-button>
        <template v-else>
          <el-button class="full" size="large" type="primary" v-if="!isApplay" @click="apply" :loading="btnLoading">{{
            $t("活动申请") }}</el-button>
          <el-button class="full" size="large" type="primary" v-else disabled>{{ $t("已申请") }}</el-button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import AllActivity from "@/components/common/AllActivity.vue";
import { action_apply_api, action_detail_api } from "@/api/action";
import { useRoute } from "vue-router";
import { ResCode } from "@/enum/ResultCode";
import { Ref, onMounted, ref } from "vue";
import { ActionItem } from "@/types/api/action";
import { ElMessage } from "element-plus";
import Loading from "@/utils/loading";
const $route = useRoute();
const isApplay = ref(false);

// 获取数据
const data: Ref<ActionItem | null> = ref(null);
const getData = async () => {
  const res = await action_detail_api({ action_id: $route.params.id as string });
  if (res.code === ResCode.success) {
    data.value = res.data;
  }
};

// 活动申请
const btnLoading = ref(false);
const apply = async () => {
  btnLoading.value = true;
  const res = await action_apply_api({ action_id: $route.params.id as string });
  btnLoading.value = false;
  if (res.code === ResCode.success) {
    ElMessage({
      type: "success",
      message: res.msg
    });
    isApplay.value = true;
  }
};

onMounted(async () => {
  Loading.show();
  await getData();
  Loading.hide();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/activityInfo.less"></style>