<template>
  <MyCommissionList v-show="!id" @showInfo="showInfo" />
  <CommissionDetails v-if="id" :id="id" @back="id = null" />
</template>

<script setup lang="ts">
import MyCommissionList from "./myCommission/MyCommissionList.vue";
import CommissionDetails from "./myCommission/CommissionDetails.vue";
import { V2TeamCommissionItem } from "@/types/api/team";
import { Ref, ref } from "vue";
const id: Ref<number | null> = ref(null);
const showInfo = (data: V2TeamCommissionItem) => {
  id.value = data.id;
};
</script>