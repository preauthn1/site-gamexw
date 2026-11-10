import { Ref, ref } from "vue";
import FacebookPixel from "./FacebookPixel";
import PixelImp from "./PixelImp";
import BaseStorage from "@/storage/BaseStorage";
import { system_channel_api } from "@/api";
import { ResCode } from "@/enum/ResultCode";
import { SystemChannelType } from "@/enum/SystemChannelType";
import KwaiPixel from "./KwaiPixel";
// 创建缓存对象
const pixelAcidStorage = new BaseStorage<string>("pixelAcid");

// pixel像素对像，对外暴露
export const pixel: Ref<PixelImp | null> = ref(null);

// 创建像素对象并赋值
export const createPixel = async (id: string | undefined) => {
  // 查找acid用这个获取相关数据
  const acid = id || pixelAcidStorage.getData();

  // 判断是否存在，不存在不执行下列操作
  if (!acid) return;

  // 存入缓存
  pixelAcidStorage.setData(acid);

  // 获取渠道数据
  const res = await system_channel_api({ acid });
  if (res.code === ResCode.success) {
    // 创建像素对象对外暴露
    if (res.data.type === SystemChannelType.facebook) {
      pixel.value = new FacebookPixel(res.data.code);
    } else if (res.data.type === SystemChannelType.kwai) {
      pixel.value = new KwaiPixel(res.data.code);
    }
  }
};
