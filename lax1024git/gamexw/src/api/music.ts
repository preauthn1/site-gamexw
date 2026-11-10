import { MusicListApiItem } from "@/types/api/music";
import { request } from "@/utils/request";
// 发送验证码
export const music_list_api = () =>
  request.post<MusicListApiItem[]>("music/list");
