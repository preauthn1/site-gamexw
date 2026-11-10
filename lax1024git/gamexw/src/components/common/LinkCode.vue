<template>
  <div class="link-box">
    <div class="link-title">{{ $t("促销信息") }}</div>
    <div class="link-content">
      <div class="code-box">
        <qr-code class="code-img" :value="props.link" ref="qrCodeRef"></qr-code>
        <div class="code-save-btn" @click="qrCodeRef?.downLoad">{{ $t("保存") }}</div>
      </div>
      <div class="link-right">
        <div class="my-link-title">{{ $t("我的链接") }}</div>
        <div class="my-link-box">
          <el-input :value="props.link" size="large" readonly></el-input>
          <t-svg name="copy" class="copy-icon" @click="$copy(props.link)"></t-svg>
        </div>
        <div class="community-list">
          <a class="community-item" v-for="item, i in communityList" :key="i" :href="item.url" target="__blank">
            <img :src="$require(item.icon)" class="community-icon">
            <div>{{ item.name }}</div>
          </a>
        </div>
      </div>
    </div>
    <slot></slot>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, Ref } from "vue";
import QrCode from "./QrCode.vue";
import lang from "@/lang";
const qrCodeRef: Ref<InstanceType<typeof QrCode> | null> = ref(null);
const props = defineProps<{
  link?: string
}>();


const communityList = computed(() => {
  const url = encodeURIComponent(props.link || "");
  const shareText = encodeURIComponent(lang.t("share_text"));
  return [
    { name: "Telegram", icon: "telegram.png", url: `https://t.me/share/url?url=${url}&text=${shareText}` },
    { name: "Instagram", icon: "Instagram.png", url: "https://www.instagram.com/" },
    { name: "WhatsApp", icon: "whatsapp.png", url: `whatsapp://send?text=${shareText + url}&via=lopscoop` },
    { name: "Facebook", icon: "Facebook2.png", url: `https://m.facebook.com/sharer/sharer.php?u=${url}&t=${shareText}` },
    { name: "Tiktok", icon: "Tiktok.png", url: "https://www.tiktok.com/explore?" },
    { name: "Youtube", icon: "Youtube.png", url: "https://www.youtube.com/" },
    { name: "Kwai", icon: "Kwai.png", url: "https://kwai-short-video-community.en.softonic.com/" },
  ];
});


</script>

<style scoped lang="less" src="@/assets/css/components/linkCode.less"></style>