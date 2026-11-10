<template>
  <div>
    <div class="svg-list">
      <div v-for="item in names" :key="item" class="svg-item">
        <t-svg :name="item" size="46px"></t-svg>
        <div class="name">{{ item }}</div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
const icons: Record<string, { default: string }> = import.meta.glob("@/assets/icon/*", { eager: true });
const names = Object.keys(icons).map(item => item.split("/").pop()?.split(".").shift());
</script>

<style scoped lang="less">
.svg-list {
  color: rgb(0, 0, 0);
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}

.svg-item {
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
}

.name {
  margin-top: 10px;
  font-size: 20px;
}
</style>