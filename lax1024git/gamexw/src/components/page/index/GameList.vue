<template>
  <div class="game-box">
    <div class="tabs-warp">
      <el-skeleton animated :loading="loading">
        <template #template>
          <div class="game-list skeleton-warp">
            <AspectRatio aspect="204/153" v-for="i in 24" :key="i">
              <el-skeleton-item variant="image" class="skeleton-item" />
            </AspectRatio>
          </div>
        </template>
        <template #default>
          <div ref="tabsBoxRef" class="tabs" :style="`--offset-top:${offsetTop}px`">
            <Tabs ref="tabsRef" v-model:active="homeActveStore.active" :shrink="!systemStore.isPhone"
              :before-change="beforeChange">
              <Tab v-for="item, index in gameData" :key="item.name">
                <template #title>
                  <div class="tab-title">
                    <img :src="item.icon" class="tabs-icon" v-show="homeActveStore.active === index">
                    <t-svg :name="item.default_icon" class="tabs-icon" size="" v-if="isSvgLink(item.default_icon)"
                      v-show="homeActveStore.active !== index"></t-svg>
                    <img :src="item.default_icon" class="tabs-icon" v-else v-show="homeActveStore.active !== index">
                    <div>{{ $t(item.name) }}</div>
                  </div>
                </template>


              </Tab>
              <template #nav-right>
                <div class="search-box m-hide" @click="$router.push('/sub-games')">
                  <t-svg name="search"></t-svg>
                </div>
              </template>
            </Tabs>
          </div>
          <div class="game-list-item-box" :sort="index" v-for="item, index in list" :key="item.name" ref="gameListRef">
            <div class="game-title-box">
              <img :src="item.icon" class="game-title-icon">
              <div class="game-title">{{ $t(item.name) }}</div>
              <div class="all" @click="$router.push('/sub-games')">{{ $t("全部") }}</div>
            </div>
            <div class="game-list" v-if="item.gameList.length">
              <GameItem :data="gameItem" v-for="gameItem in item.gameList" :key="gameItem.id"></GameItem>
            </div>
            <Empty v-else class="empty" :loading="item.loading"></Empty>
            <More @loadmore="loadMore(item)" :loading="item.loading"
              v-if="item.page !== -1 && item.gameList.length !== 0"></More>
          </div>
        </template>
      </el-skeleton>



    </div>
  </div>
</template>

<script setup lang="ts">
import { Tab, Tabs, TabsInstance } from "vant";
import { Ref, computed, nextTick, onActivated, onDeactivated, onMounted, onUnmounted, ref, watch } from "vue";
import GameItem from "@/components/common/GameItem.vue";
import More from "@/components/common/More.vue";
import useStore from "@/store";
import isSvgLink from "@/utils/isSvgLink";
import { game_list_api } from "@/api/games";
import { GameItem as GameItemType, GameTypeItem } from "@/types/api/games";
import antiShake from "@/utils/antiShake";
import { useRoute, useRouter } from "vue-router";
import bus from "@/utils/bus";
import { OpenState } from "@/enum/OpenState";
import ApiStorage from "@/storage/ApiStorage";

const { systemStore, downloadTipStore, homeActveStore, gameTypeStore } = useStore();
const $route = useRoute();
const $router = useRouter();

// isActivated 是否在本页面
const isActivated = ref(true);


// 格式化tabs

// 距离顶部的距离
const offsetTop = ref(0);
// tabs上下文对象
const tabsRef: Ref<TabsInstance | null> = ref(null);

// tabsbox 元素对象
const tabsBoxRef: Ref<HTMLElement | null> = ref(null);

// 游戏列表元素对象
const gameListRef: Ref<HTMLElement[] | null> = ref(null);

// 监听滚动的函数
const scrollTo = (e: number) => {
  if (!gameListRef.value) return;
  // 重新给数组排序，vue切换会乱掉
  gameListRef.value.sort((a, b) => Number(a.getAttribute("sort")) - Number(b.getAttribute("sort")));

  // 改变选中的字段
  homeActveStore.active = e;
  // 判断有没有展示这个列表，没有默认0
  if (!gameListRef.value[e]) e = 0;
  // 距离顶部的距离
  const top = (tabsBoxRef.value?.offsetHeight || 0) + offsetTop.value;
  // 滚动到制定距离
  window.scrollTo({
    top: gameListRef.value[e].getBoundingClientRect().top + window.scrollY - top
  });
};

// beforeChange执行切换前的操作
const beforeChange = (e: number) => {
  // 判断要切换的字段是否在滑动列表内，在的话给gameActiveData复制会自动展示，清空展示滚动列表
  if (!gameData.value[e].isScroll) {
    gameActiveData.value = gameData.value[e];
    $router.replace(`?current=${e}`);
    // 拉取接口
    gameActiveData.value.page = 1;
    gameActiveData.value.gameList = [];
    gameActiveData.value.loading = true;
    getGameList(gameActiveData.value).then(() => {
      gameActiveData.value && (gameActiveData.value.loading = false);
    });
  } else {
    gameActiveData.value = null;
  }
  // 渲染完进行 滚动操作
  nextTick(() => {
    scrollTo(e);
  });
};


// 监听路由，进行切换
watch(() => $route.query, (query) => {
  nextTick(() => {
    if (!isActivated.value) return;
    if (!homeActveStore.isWatchRouter) return;
    if (query.current) {
      beforeChange(Number(query.current));
    } else {
      window.scrollTo(0, 0);
    }
  });

});


// 滚动时间监听，加上防抖函数
const scroll = antiShake(() => {
  if (!gameListRef.value) return;
  if (gameActiveData.value) return;
  const top = (tabsBoxRef.value?.offsetHeight || 0) + offsetTop.value;
  let index = 0;
  gameListRef.value.forEach((item, i) => {
    if ((item.getBoundingClientRect().top - top) < 10) {
      index = i;
    }
  });
  homeActveStore.active = index;
  $router.replace(`?current=${index}`);
}, 66);


// 获取距离顶部定位距离
const initTop = () => {
  const headEl = document.getElementById("head");
  if (!headEl) return;
  const headClientRect = headEl.getBoundingClientRect();
  offsetTop.value = headClientRect.top + headClientRect.height;
};

// 监听下载显示情况,获取距离,避免样式混乱
watch(() => downloadTipStore.isShow, () => nextTick(initTop));

// 监听收藏广播事件
const collectEvent = (data: unknown) => {
  const eventData = data as GameItemType;
  gameData.value.forEach(item => {
    item.gameList.forEach(gameItem => {
      if (gameItem.id == eventData.id) {
        gameItem.is_collect = eventData.is_collect;
      }
    });
    if (item.id === "collect_id" && eventData.is_collect !== OpenState.open) {
      item.gameList = item.gameList.filter(gameItem => gameItem.id !== eventData.id);
    }
  });
};

// 清除副作用
onMounted(() => {
  bus.on("game-collect", collectEvent);
});
onUnmounted(() => {
  bus.off("game-collect", collectEvent);
});
onActivated(() => {
  initTop();
  window.addEventListener("resize", initTop);
  window.addEventListener("scroll", scroll);
  isActivated.value = true;
});
onDeactivated(() => {
  window.removeEventListener("resize", initTop);
  window.removeEventListener("scroll", scroll);
  isActivated.value = false;
});




// 请求数据
const loading = ref(true);
// item里面加的字段数据
type GameDataItem = GameTypeItem & { page: number, limit: number, gameList: GameItemType[], isScroll: boolean, loading?: boolean }

// 游戏类型加列表，重新定义了数据结构
const gameData: Ref<GameDataItem[]> = ref([]);

// 列表只展示一个的时候存储的数据
const gameActiveData: Ref<GameDataItem | null> = ref(null);

// 获取游戏列表
const getGameList = async (item: GameDataItem) => {
  const page = item.page;
  const fetch = new ApiStorage({
    api: () => game_list_api({
      limit: item.limit,
      page,
      hot: item.tag === "hot" ? 1 : "",
      t_id: item.id,
      tc_id: 0,
    }),
    key: String(item.tag) + String(item.id),
    success(data) {
      item.gameList = page === 1 ? data : [...item.gameList, ...data];
      // page = -1 不会显示加载更多
      item.page = data.length < item.limit ? -1 : item.page;
    },
  });
  if (item.page === 1 && item.id !== "collect_id") {
    await fetch.getData();
  } else {
    await fetch.getApiData();
  }
};
const loadMore = async (item: GameDataItem) => {
  item.page++;
  item.loading = true;
  await getGameList(item);
  item.loading = false;
};

// 获取需要渲染的游戏列表
const list = computed(() => {
  return gameActiveData.value ? [gameActiveData.value] : gameData.value.filter(item => item.isScroll);
});

// 监听gameTypeStore.data，有数据进行下次请求
watch(() => gameTypeStore.data, async (v) => {
  // 监听数值不一样
  if (v.length) {
    // 增加字段isScroll是否展示在首页滑动列表中
    gameData.value = v.map(item => ({ ...item, page: 1, limit: 12, gameList: [], isScroll: item.id !== "collect_id" }));

    // 一次性请求全部数据接口，加上loading显示控制,去掉isScroll的请求
    loading.value = true;
    await Promise.all(gameData.value.filter(item => item.isScroll).map(item => getGameList(item)));
    loading.value = false;

    // 判断是否有路由上是否有current，有责进行选中操作
    Number($route.query.current) && nextTick(() => beforeChange(Number($route.query.current)));
  }
}, { immediate: true });

</script>

<style scoped lang="less" src="@/assets/css/pages/index/gameList.less"></style>