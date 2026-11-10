import { system_info_api, system_info_new_api } from "@/api";
import { SystemInfoApi, SystemInfoNewApi } from "@/types/api";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import useStore from "@/store";
import DataBaseStorage from "@/storage/DataBaseStorage";

const systemStorage = new DataBaseStorage<SystemInfoApi>("system_info");
const systemNewStorage = new DataBaseStorage<SystemInfoNewApi>(
  "system_info_new"
);

const initData = (res: SystemInfoApi, resNew: SystemInfoNewApi) => {
  // 新字段覆盖老字段
  for (const key in res.lang) {
    res.lang[key] = { ...res.lang[key], ...resNew.lang[key] };
  }
  res.data = { ...res.data, ...resNew.site };
  // 设置语言
  const locale = localStorage.getItem("locale");
  lang.setLang(res.lang);
  lang.setLocale(locale || res.locale);

  // 设置状态管理数据
  const { systemStore } = useStore();
  systemStore.upDateSystemData({ ...res, lang: {} });
  systemStore.upDateSystemDataNew({ ...resNew, lang: {} });
};

export const getStorageData = async () => {
  const res = await systemStorage.getData();
  const resNew = await systemNewStorage.getData();
  if (res && resNew) {
    initData(res, resNew);
    return true;
  } else {
    return false;
  }
};
export const getData = async () => {
  const [res, resNew] = await Promise.all([
    system_info_api(),
    system_info_new_api(),
  ]);
  if (res.code === ResCode.success && resNew.code === ResCode.success) {
    initData(res, resNew);
    systemStorage.setData(res);
    systemNewStorage.setData(resNew);
  }
};
