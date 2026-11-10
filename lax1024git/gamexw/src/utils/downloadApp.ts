import { DeviceType } from "@/enum/DeviceType";
import useStore from "@/store";
export default () => {
  const { systemStore,envStore } = useStore();
  if (envStore.getDevice() === DeviceType.iosWeb) {
    location.href = systemStore.systemData?.data.ios_download_url as string;
  } else {
    location.href = systemStore.systemData?.data.android_download_url as string;
  }
};
