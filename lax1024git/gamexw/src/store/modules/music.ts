import { music_list_api } from "@/api/music";
import { LoopState } from "@/enum/Music";
import ApiStorage from "@/storage/ApiStorage";
import BaseStorage from "@/storage/BaseStorage";
import { MusicListApiItem } from "@/types/api/music";
import { defineStore } from "pinia";
const myMusicStorage = new BaseStorage<number[]>("myMusicList", true);

export default defineStore("music", {
  state: () => {
    return {
      musicList: [] as MusicListApiItem[],
      myMusicListIds: [] as number[],
      context: document.createElement("audio"),
      // 循环，单曲循环，全部循环，随机播放
      loopState: LoopState.allLoops,
      isPlay: false, //是否播放
      currentData: null as MusicListApiItem | null,
      duration: 0,
      currentDuration: 0,
      // 音量
      volume: 0.5,
      // 是否静音
      muted: false,
    };
  },
  actions: {
    // 设置静音状态
    setMuted(muted: boolean) {
      this.muted = muted;
      this.context.muted = muted;
    },
    // 获取我的游戏列表
    getMyMusicList() {
      return this.musicList.filter((item) =>
        this.myMusicListIds.includes(item.id)
      );
    },
    // 设置音量
    setVolume(volume: number) {
      this.volume = volume;
      this.context.volume = volume;
      // 是静音状态接触静音
      if (this.muted) this.setMuted(false);
    },

    setCurrentData(data: MusicListApiItem, isPlay = false) {
      // 设置数据
      this.currentData = data;
      // 设置连接
      this.context.src = this.currentData.file;
      // 设置时间0
      this.setCurrentDuration(0);
      this.myMusicListIds.push(data.id);

      // 数组去重
      this.myMusicListIds = Array.from(new Set(this.myMusicListIds));

      // 存储缓存
      myMusicStorage.setData(this.myMusicListIds);
      if (isPlay) this.play();
    },

    // 删除
    deleteMyMusic(id: number) {
      this.myMusicListIds = this.myMusicListIds.filter((item) => item !== id);
      myMusicStorage.setData(this.myMusicListIds);
    },

    // 获取数据
    async getData() {
      const fatch = new ApiStorage({
        api: () => music_list_api(),
        success: (data) => {
          this.musicList = data.sort((a, b) => (a.name > b.name ? 1 : -1));
          // 设置默认歌曲，从我的列表中取出，没有取第一个
          const musicData = this.musicList.find((item) =>
            this.myMusicListIds.includes(item.id)
          );
          this.setCurrentData(musicData || this.musicList[0]);
        },
      });
      await fatch.getData();
    },
    // 初始化播放器
    async init() {
      this.myMusicListIds = myMusicStorage.getData() || [];
      this.setVolume(0.5);

      // 加载事件
      this.context.addEventListener("loadedmetadata", () => {
        this.duration = this.context.duration;
      });

      // 时间更新
      this.context.addEventListener("timeupdate", () => {
        this.currentDuration = this.context.currentTime;
      });
      // 播放事件
      this.context.addEventListener("play", () => {
        this.isPlay = true;
      });
      // 暂停事件
      this.context.addEventListener("pause", () => {
        this.isPlay = false;
      });
      // 结束事件
      this.context.addEventListener("ended", () => {
        this.isPlay = false;
        // 单曲循环
        if (this.loopState === LoopState.singleLoop) {
          this.play();
        } else {
          this.next();
        }
      });
      await this.getData();
    },
    // 播放
    play() {
      // 创建音乐播放器
      this.context.play();
    },
    // 暂停
    pause() {
      this.context.pause();
    },

    // 设置时间
    setCurrentDuration(time: number) {
      this.context.currentTime = time;
    },
    // 随机播放 排除当前歌曲
    random() {
      const list = this.getMyMusicList().filter(
        (item) => item.id !== this.currentData?.id
      );
      const data = list[Math.floor(Math.random() * list.length)];
      if (data) {
        this.setCurrentData(data, true);
      }
    },
    // 下一首
    next() {
      const list = this.getMyMusicList();
      if (this.loopState !== LoopState.randomPlay) {
        const i = list.findIndex((item) => item.id === this.currentData?.id);
        this.setCurrentData(list[i + 1] || list[0], true);
      } else {
        this.random();
      }
    },
    // 下一首
    pre() {
      const list = this.getMyMusicList();
      if (this.loopState !== LoopState.randomPlay) {
        const i = list.findIndex((item) => item.id === this.currentData?.id);
        this.setCurrentData(list[i] || list[list.length - 1], true);
      } else {
        this.random();
      }
    },

    // 循环
    changeLoopState() {
      const loopStateList = [
        LoopState.allLoops,
        LoopState.singleLoop,
        LoopState.randomPlay,
      ];
      const i = loopStateList.findIndex((item) => item === this.loopState);
      this.loopState = loopStateList[i + 1] || loopStateList[0];
    },
  },
});
