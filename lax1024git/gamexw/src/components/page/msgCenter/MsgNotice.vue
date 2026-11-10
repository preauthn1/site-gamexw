<template>
  <div class="content-box">
    <MsgItem v-for="item in msgList" :key="item.id" :title="item.title" :content="item.content" :time="item.add_time"
      :is-new="item.read === 0" class="msg-item">
      <template #bottom v-if="item.read === 0">
        <el-button type="primary" @click="setread(item)" :loading="lodings[item.id]">{{ $t("标记为已读") }}</el-button>
      </template>
    </MsgItem>
    <Empty class="empty" v-if="msgList.length === 0" :loading="loading"></Empty>
    <More v-else-if="params.page !== -1" :loading="loading" @loadmore="loadmore"></More>
  </div>
</template>

<script setup lang="ts">
import { message_list_api, message_setread_api } from "@/api/message";
import Empty from "@/components/common/Empty.vue";
import More from "@/components/common/More.vue";
import MsgItem from "@/components/common/MsgItem.vue";
import { ResCode } from "@/enum/ResultCode";
import { MessageListItem } from "@/types/api/message";
import { Ref, ref, watch } from "vue";
import { useRoute } from "vue-router";
const $route = useRoute();

// 标记为已读

const lodings: Ref<Record<string, boolean>> = ref({});
const setread = async (item: MessageListItem) => {
  lodings.value[item.id] = true;
  const res = await message_setread_api({ msg_id: item.id });
  lodings[item.id] = false;
  if (res.code === ResCode.success) {
    item.read = 1;
  }
};

// 加载数据
const loading = ref(false);
const params = ref({
  page: 1,
  limit: 10
});
const msgList: Ref<MessageListItem[]> = ref([]);

const getMsgList = async () => {
  const res = await message_list_api({ ...params.value, unread: 0 });
  if (res.code === ResCode.success) {
    msgList.value = [...msgList.value, ...res.data];
    if (res.data.length < params.value.limit) {
      params.value.page = -1;
    }
  }
};


const loadmore = async () => {
  params.value.page++;
  loading.value = true;
  await getMsgList();
  loading.value = false;
};


// 每次切换都重新加载
watch(() => $route.fullPath, async v => {
  if (v === "/msgCenter?current=2") {
    loading.value = true;
    msgList.value = [];
    params.value.page = 1;
    await getMsgList();
    loading.value = false;
  }
}, { immediate: true });
</script>

<style scoped lang="less" src="@/assets/css/pages/msgCenter/msg.less"></style>