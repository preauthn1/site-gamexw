<template>
  <Popup v-model:show="isShow" @closed="emit('closed')" :position="systemStore.isPhone ? 'bottom' : 'center'">
    <div class="title">
      <t-svg name="left" class="left-icon pc-hide" @click="isShow = false"></t-svg>
      {{ $t("团队列表") }}
    </div>
    <div class="top-input-box">
      <el-select size="large" v-model="form.level" class="round select">
        <el-option :label="$t(item.name)" :value="item.value" v-for="item in levelList" :key="item.value" />
      </el-select>
      <el-input size="large" class="round search" :suffix-icon="Search" :placeholder="$t('账户')"
        v-model="form.username"></el-input>
    </div>
    <div class="content">
      <el-table :data="data?.list" size="large" class="t-table" v-if="data?.list.length">
        <el-table-column prop="username" :label="$t('用户名')" />
        <el-table-column :label="$t('级别')">
          <template #default="scope">
            {{ $t(findLevel(scope.row.leve)?.name || "") }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('总充值')">
          <template #default="scope">
            {{ $numInit(scope.row.total_offline) }}
          </template>
        </el-table-column>
        <el-table-column :label="$t('账户余额')">
          <template #default="scope">
            {{ $numInit(scope.row.balance) }}
          </template>
        </el-table-column>
        <template #append v-if="form.page !== -1">
          <More @loadmore="loadMore" :loading="loading"></More>
        </template>
      </el-table>
      <Empty v-else class="empty" :loading="loading"></Empty>
    </div>
  </Popup>
</template>

<script setup lang="ts">
import { ref, onMounted, Ref, watch } from "vue";
import Empty from "./Empty.vue";
import useStore from "@/store";
import { Search } from "@element-plus/icons-vue";
import { team_user_api } from "@/api/team";
import { ResCode } from "@/enum/ResultCode";
import { TeamUser } from "@/types/api/team";
import antiShake from "@/utils/antiShake";
import { levelList, findLevel } from "@/data/levelList";

const { systemStore } = useStore();
const isShow = ref(false);
const emit = defineEmits(["closed"]);
onMounted(() => {
  isShow.value = true;
});


const form = ref({
  page: 1,
  limit: 10,
  level: "4",
  username: ""
});
const loading = ref(false);
const data: Ref<TeamUser | null> = ref(null);
const getData = async () => {
  loading.value = true;
  const res = await team_user_api(form.value);
  loading.value = false;
  if (res.code === ResCode.success) {
    if (form.value.page == 1) {
      data.value = res.data;
    } else if (data.value) {
      data.value.list = [...data.value.list, ...res.data.list];
    }
    if (res.data.list.length < form.value.limit) {
      form.value.page = -1;
    }
  }
};

watch(() => form.value.level, () => {
  form.value.page = 1;
  data.value = null;
  getData();
});

watch(() => form.value.username, antiShake(() => {
  form.value.page = 1;
  data.value = null;
  getData();
}));


const loadMore = () => {
  form.value.page++;
  getData();
};
onMounted(() => {
  getData();
});
</script>

<style scoped lang="less" src="@/assets/css/components/teamUser.less"></style>