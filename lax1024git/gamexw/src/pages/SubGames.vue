<template>
  <div class="page-box">
    <NavBar class="nav-bar" :title="$t('全部游戏')"></NavBar>
    <div class="search-box">
      <el-input class="search-input" size="large" :placeholder="$t('搜索游戏')" :suffix-icon="Search"
        v-model="params.keywords"></el-input>
      <el-button size="large" type="primary" class="search-btn" @click="search">{{ $t("搜索") }}</el-button>
    </div>
    <PcTabs :tabs-list="gameThirdList.map(item => ({ icon: item.icon }))" v-model="active" class="tabs-box"
      :isRouter="false" :loading="tabLoading" :isIcon="true">
      <div class="game-list-box">
        <div class="type-list">
          <el-button type="primary" class="type-item" v-for="item, index in topTabsList" :key="item.name"
            :plain="activeTop !== index" @click="activeTop = index">{{ $t(item.name) }}</el-button>
        </div>
        <el-skeleton animated :loading="gameLoading">
          <template #template>
            <div class="game-list">
              <AspectRatio aspect="204/153" class="game-item" v-for="i in 30" :key="i">
                <el-skeleton-item variant="image"
                  style="width: 100%;height:100%;border-radius: var(--border-radius);" />
              </AspectRatio>
            </div>

          </template>
          <div class="game-list">
            <GameItem v-for="item in gameList" :key="item.id" :data="item"></GameItem>
          </div>
          <Empty class="empty" v-if="gameList.length === 0"></Empty>
          <More @loadmore="loadMore" :loading="moreLoading" v-if="params.page !== -1 && gameList.length !== 0"></More>
        </el-skeleton>
      </div>
    </PcTabs>
  </div>
</template>

<script setup lang="ts">
import NavBar from "@/components/common/NavBar.vue";
import { Search } from "@element-plus/icons-vue";
import GameItem from "@/components/common/GameItem.vue";
import PcTabs from "@/components/common/PcTabs.vue";
import { game_list_api, game_third_api } from "@/api/games";
import { Ref, onMounted, onUnmounted, ref, watch } from "vue";
import { GameItem as GameItemType, GameThirdItem } from "@/types/api/games";
import More from "@/components/common/More.vue";
import bus from "@/utils/bus";
import ApiStorage from "@/storage/ApiStorage";
const active = ref(0);
const activeTop = ref(0);
const tabLoading = ref(false);
const gameLoading = ref(false);
const moreLoading = ref(false);
// 三方品牌
const gameThirdList: Ref<GameThirdItem[]> = ref([]);
const getGameThirdList = async () => {
  const fetch = new ApiStorage({
    api: () => game_third_api(),
    success: data => {
      gameThirdList.value = data;
    }
  });
  tabLoading.value = true;
  await fetch.getData();
  tabLoading.value = false;
};

const topTabsList = ref([
  { name: "全部", params: {} },
  { name: "热门", params: { hot: 1 } },
  { name: "收藏", params: { t_id: "collect_id" } },
]);
const params = ref({
  limit: 30,
  page: 1,
  keywords: ""
});

// 获取游戏列表
const gameList: Ref<GameItemType[]> = ref([]);
const getGameList = async (isStorge = false) => {
  const fetchParams = {
    tc_id: gameThirdList.value[active.value].tc_id,
    ...topTabsList.value[activeTop.value].params,
    ...params.value
  };
  const fetch = new ApiStorage({
    api: () => game_list_api(fetchParams),
    key: JSON.stringify(fetchParams),
    success: data => {
      gameList.value = fetchParams.page === 1 ? data : [...gameList.value, ...data];
      // page = -1 不会显示加载更多
      params.value.page = data.length < fetchParams.limit ? -1 : fetchParams.page;
    }
  });
  if (isStorge) {
    await fetch.getData();
  } else {
    await fetch.getApiData();
  }
};
const loadMore = async () => {
  params.value.page++;
  moreLoading.value = true;
  await getGameList();
  moreLoading.value = false;
};
const search = async () => {
  gameList.value = [];
  params.value.page = 1;
  gameLoading.value = true;
  await getGameList();
  gameLoading.value = false;
};

watch([active, activeTop], async () => {
  params.value.keywords = "";
  gameList.value = [];
  params.value.page = 1;
  gameLoading.value = true;
  await getGameList(true);
  gameLoading.value = false;
});

// 监听收藏广播事件
const collectEvent = (data: unknown) => {
  const eventData = data as GameItemType;
  gameList.value.forEach(gameItem => {
    if (gameItem.id == eventData.id) {
      gameItem.is_collect = eventData.is_collect;
    }
  });
};

onMounted(async () => {
  bus.on("game-collect", collectEvent);
  gameLoading.value = true;
  await getGameThirdList();
  watch(gameThirdList, async v => {
    if (v.length) {
      await getGameList(true);
      gameLoading.value = false;
    }
  }, { immediate: true });
});
onUnmounted(() => {
  bus.off("game-collect", collectEvent);
});
</script>

<style scoped lang="less" src="@/assets/css/pages/subGames.less"></style>