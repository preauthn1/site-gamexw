<template>
  <div class="tabs-box">
    <div class="tabs-menu-box">
      <el-skeleton animated :loading="props.loading">
        <template #template>
          <el-skeleton-item variant="button" class="tabs-menu-item" v-for="i in 8" :key="i"
            :class="{ 'tabs-menu-item-have-icon': props.isIcon }" />
        </template>
        <div class="tabs-menu-item" @click="tabItem(item, i)"
          :class="{ active: isRouter ? (item.url === $route.path) : (props.modelValue === i), 'tabs-menu-item-have-icon': item.icon }"
          v-for="item, i in props.tabsList" :key="i">
          <t-svg :name="item.icon" v-if="isSvgLink(item.icon || '')" class="tabs-menu-item-icon" size=""></t-svg>
          <img :src="item.icon" class="tabs-menu-item-icon" v-else>
          <span v-if="item.name">{{ $t(item.name) }}</span>
        </div>
      </el-skeleton>
    </div>
    <div class="tabs-list-box">
      <slot></slot>
    </div>
  </div>
</template>

<script setup lang="ts">
import isSvgLink from "@/utils/isSvgLink";
import { PropType } from "vue";
import { useRouter } from "vue-router";
const $router = useRouter();
type TabItem = { name?: string, url?: string, icon?: string }
const props = defineProps({
  tabsList: {
    type: Array as PropType<TabItem[]>,
    default: () => []
  },
  isRouter: {
    type: Boolean,
    default: true
  },
  modelValue: {
    type: Number,
    default: 0
  },
  loading: {
    type: Boolean,
    default: false
  },
  isIcon: {
    type: Boolean,
    default: false
  }
});
const emit = defineEmits(["update:modelValue"]);

const tabItem = (item: TabItem, index: number) => {
  if (props.isRouter) {
    item.url && $router.replace(item.url);
  } else {
    emit("update:modelValue", index);
  }
};
</script>

<style scoped lang="less">
.tabs-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.tabs-menu-box {
  margin-right: 20px;
}

.tabs-list-box {
  flex: 1;
  overflow: hidden;
}

.tabs-menu-item {
  width: 130px;
  height: 65px;
  padding: 20px 14px;
  background-color: var(--color1);
  border-radius: 5px;
  cursor: pointer;
  user-select: none;
  .flex-center();
  flex-direction: column;
  margin-bottom: 10px;
  text-align: center;

  .md({
    background-color: var(--color17);
  });

&:last-child {
  margin-bottom: 0;
}

&.active {
  background-color: var(--color4);
  color: var(--color1);
}
}

.tabs-menu-item-have-icon {
  height: 89px
}

.tabs-menu-item-icon {
  width: 48px;
  height: 35px;
  object-fit: contain;
}
</style>