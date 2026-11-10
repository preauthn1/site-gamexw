<template>
  <Popup v-model:show="isShow" @closed="emit('closed')" @touchmove.stop @touchend.stop @touchstart.stop>
    <div class="box">
      <swiper-container class="swiper" :loop="false" :centeredSlides="true" :allowTouchMove="false" spaceBetween="10px"
        :navigation="{
          nextEl: '.announcement-right', prevEl: '.announcement-left'
        }">
        <swiper-slide v-for="item in list" :key="item.id">
          <div class="content html" v-html="item.content"></div>
          <el-button class="full btn" type="primary" size="large" @click="$openLink(item.jump_url); isShow = false">{{
            $t('了解')
          }}</el-button>
        </swiper-slide>
      </swiper-container>
      <div class="tip" @click="todayHide">{{ $t("今天不再提醒") }}</div>
      <t-svg name="left" class="announcement-left"></t-svg>
      <t-svg name="right" class="announcement-right"></t-svg>
    </div>
  </Popup>
</template>

<script setup lang="ts">
import { notice_list_api } from "@/api";
import { ResCode } from "@/enum/ResultCode";
import { NoticeListApiItem } from "@/types/api";
import { ref, onMounted, Ref } from "vue";
import moment from "moment";
import BaseStorage from "@/storage/BaseStorage";
const timeStorage = new BaseStorage("announcementTime");
const isShow = ref(false);
const emit = defineEmits(["closed"]);
const list: Ref<NoticeListApiItem[]> = ref([]);
const getData = async () => {
  const res = await notice_list_api({
    page: 1,
    limit: 10,
    type: 2,
  });
  if (res.code === ResCode.success) {
    list.value = res.data;
    if (res.data.length) {
      isShow.value = true;
    }

  }
};

const todayHide = () => {
  // 设置时间
  timeStorage.setData(moment().format("YYYY-MM-DD"));
  isShow.value = false;
};

onMounted(async () => {
  // 判断隐藏时间
  if (timeStorage.getData() === moment().format("YYYY-MM-DD")) return;
  await getData();
});
</script>

<style scoped lang="less" src="@/assets/css/components/announcement.less"></style>