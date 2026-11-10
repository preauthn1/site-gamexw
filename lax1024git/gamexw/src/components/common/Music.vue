<template>
  <Popup v-model:show="isShow" @closed="emit('closed')">
    <div class="title">{{ $t("音乐") }}</div>
    <div class="volume-box">
      <span class="volume-name">{{ $t("音量") }}</span>
      <el-slider class="volume-slider" v-model="volume" :max="1" :min="0" :step="0.01"  :format-tooltip="value => `${Math.floor(value*100)}%` "/>
      <t-svg name="yinliang" class="volume-icon" v-if="!musicStore.muted" @click="musicStore.setMuted(true)"></t-svg>
      <t-svg name="jingyin" class="volume-icon" v-else @click="musicStore.setMuted(false)"></t-svg>
    </div>
    <div class="music-ctr-box">
      <div class="music-ctr-name">{{ musicStore.currentData?.name }}</div>
      <div class="music-steep-box">
        <div class="time">{{ durationInit(musicStore.currentDuration) }}</div>
        <el-slider class="steep-slider" v-model="time" :max="musicStore.duration"
          :format-tooltip="value => durationInit(value)" @change="changeTime" @input="input" />
        <div class="time">{{ durationInit(musicStore.duration) }}</div>
      </div>
      <div class="ctr-warp">
        <div class="loop-box" @click="musicStore.changeLoopState">
          <t-svg name="xunhuan" class="loop-icon" v-if="musicStore.loopState === LoopState.allLoops"></t-svg>
          <t-svg name="suiji" class="loop-icon" v-if="musicStore.loopState === LoopState.randomPlay"></t-svg>
          <t-svg name="danqu" class="loop-icon" v-if="musicStore.loopState === LoopState.singleLoop"></t-svg>
          <span>{{ $t("循环") }}</span>
        </div>
        <div class="play-list">
          <div class="play-item" @click="musicStore.pre">
            <t-svg name="houtui"></t-svg>
          </div>

          <!-- 播放 -->
          <div class="play-item" @click="musicStore.play" v-if="!musicStore.isPlay">
            <t-svg name="bofang"></t-svg>
          </div>
          <!-- 暂停 -->
          <div class="play-item" @click="musicStore.pause" v-else>
            <t-svg name="zanting"></t-svg>
          </div>

          <div class="play-item" @click="musicStore.next">
            <t-svg name="kuaijin"></t-svg>
          </div>
        </div>
        <div class="down-box">
          <div>{{ musicStore.myMusicListIds.length }}</div>
          <div>{{ $t("已下载") }}</div>
        </div>
      </div>
    </div>
    <div class="music-list-box">
      <Tabs v-model:active="active" class="tabs" animated>
        <Tab :title="$t('系统音乐')">
          <div class="music-list">
            <div class="music-item" v-for="item, index in musicStore.musicList" :key="item.id" @click="musicStore.setCurrentData(item,true)">
              <div class="item-name-box">
                <t-svg name="music" class="music-icon" :class="{play:musicStore.isPlay}" v-if="item.id === musicStore.currentData?.id"></t-svg>
                <span v-else>{{ index+1 }}</span>
                <span class="item-name">{{ item.name }}</span>
                <span>{{ numInit(Number(item.size) / 1024 / 1024) }}M</span>
              </div>
              <t-svg name="duigou" v-if="musicStore.myMusicListIds.includes(item.id)" class="right-icon"></t-svg>
              <t-svg name="xiazai" v-else class="right-icon"></t-svg>
            </div>
          </div>
        </Tab>
        <Tab :title="$t('我的音乐')">
          <div class="music-list">
            <div class="music-item" v-for="item, index in musicStore.getMyMusicList()" :key="item.id" @click="musicStore.setCurrentData(item,true)">
              <div class="item-name-box">
                <t-svg name="music" class="music-icon" :class="{play:musicStore.isPlay}" v-if="item.id === musicStore.currentData?.id"></t-svg>
                <span v-else>{{ index+1 }}</span>
                <span class="item-name">{{ item.name }}</span>
                <span>{{ numInit(Number(item.size) / 1024 / 1024) }}M</span>
              </div>
              <t-svg name="delete" v-if="musicStore.myMusicListIds.includes(item.id)" class="right-icon" @click.stop.prevent="deleteMusic(item.id)"></t-svg>
              <t-svg name="xiazai" v-else class="right-icon"></t-svg>
            </div>
          </div>
        </Tab>
      </Tabs>
    </div>
  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from "vue";
import { Tab, Tabs } from "vant";
import useStore from "@/store";
import numInit from "@/utils/numInit";
import { LoopState } from "@/enum/Music";
import { openLink } from "@/utils/openLink";
import { ElMessage } from "element-plus";
import lang from "@/lang";
const { musicStore } = useStore();
const isShow = ref(false);
const active = ref(0);
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});

const durationInit = (duration: number) => {
  const m = Math.floor(duration / 60);
  const s = Math.floor(duration % 60);
  return `${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
};

// 时间操作相关
const isInput = ref(false);
const time = ref(0);
const changeTime = () => {
  musicStore.setCurrentDuration(time.value);
  isInput.value = false;
};
const input = () => {
  isInput.value = true;
};
watch(() => musicStore.currentDuration, v => {
  if (isInput.value) return;
  time.value = v;
});

// 音量相关
const volume = ref(musicStore.volume);
watch(volume,v=>{
  musicStore.setVolume(v);
});

// 删除我的音乐
const deleteMusic = (id:number)=>{
  if(musicStore.myMusicListIds.length===1){
    ElMessage({
      type:"warning",
      message: lang.t("最后一首歌无法删除")
    });
    return;
  }
  openLink("/confirm",{
    content: "你想要删除吗？",
    confirm:()=>{
      musicStore.deleteMyMusic(id);
    }
  });
};
</script>

<style scoped lang="less" src="@/assets/css/components/music.less"></style>