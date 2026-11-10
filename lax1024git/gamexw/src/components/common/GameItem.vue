<template>
  <AspectRatio aspect="204/153" class="game-item">
    <t-img :src="props.data.gi_headimg" class="game-cover" @click="$router.push(`/gameInfo/${props.data.id}`)" />
    <collection-icon class="collection-icon" :collect="props.data.is_collect === OpenState.open && userStore.isLogin"
      @click="collect"></collection-icon>
  </AspectRatio>
</template>

<script setup lang="ts">
import { GameItem } from "@/types/api/games";
import AspectRatio from "./AspectRatio.vue";
import CollectionIcon from "./CollectionIcon.vue";
import { OpenState } from "@/enum/OpenState";
import { collect_collect_api } from "@/api/games";
import { CollectType } from "@/enum/CollectType";
import bus from "@/utils/bus";
import useStore from "@/store";
import { openLink } from "@/utils/openLink";
const { userStore } = useStore();
const props = defineProps<{
  data: GameItem
}>();
const collect = async () => {
  if (!userStore.isLogin) {
    openLink("/login");
    return;
  }
  collect_collect_api({
    type: CollectType.game,
    other_id: props.data.id
  });
  // eslint-disable-next-line vue/no-mutating-props
  props.data.is_collect = props.data.is_collect === OpenState.open ? OpenState.close : OpenState.open;
  bus.emit("game-collect", props.data);

};
</script>

<style scoped lang="less" src="@/assets/css/components/gameItem.less"></style>