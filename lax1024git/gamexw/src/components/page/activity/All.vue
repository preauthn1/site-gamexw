<template>
  <el-skeleton animated :loading="activityStore.loading">
    <template #template>
      <div class="card-box">
        <div class="card-list">
          <AspectRatio aspect="215/475" v-for="i in 10" :key="i">
            <el-skeleton-item variant="image" class="activity-img" />
          </AspectRatio>
        </div>
      </div>
    </template>
    <div class="card-box">
      <div class="card-list" v-if="activityStore.activityList.length">
        <AspectRatio aspect="215/475" v-for="item, index in activityStore.activityList" :key="index">
          <t-img :src="item.c_img" class="activity-img" @click="tabItem(item)" />
        </AspectRatio>
      </div>
      <Empty v-else class="empty"></Empty>
    </div>
  </el-skeleton>

</template>

<script setup lang="ts">
import AspectRatio from "@/components/common/AspectRatio.vue";
import useStore from "@/store";
import { ActionItem } from "@/types/api/action";
import { openLink } from "@/utils/openLink";
import { onMounted } from "vue";
import { useRouter } from "vue-router";
import Empty from "@/components/common/Empty.vue";
const $router = useRouter();
const { activityStore } = useStore();
const tabItem = (item: ActionItem) => {
  if (item.jump_url) {
    openLink(item.jump_url);
  } else {
    $router.push(`/activityInfo/${item.id}`);
  }
};

// 请求数据
onMounted(async () => {
  activityStore.getList();
});
</script>

<style scoped lang="less" src="@/assets/css/pages/activity/all.less"></style>
