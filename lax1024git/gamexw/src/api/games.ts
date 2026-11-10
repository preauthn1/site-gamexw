import { GameItem, GameThirdItem, GameType } from "@/types/api/games";
import { request } from "@/utils/request";

// 游戏类型
export const game_get_type_api = () => request.post<GameType>("game/get_type");

export const game_list_api = (data: {
  page: number | string;
  limit: number | string;
  t_id?: number | string;
  tc_id?: number | string;
  hot?: number | string;
}) => request.post<GameItem[]>("game/list", data);

// 获取游戏收藏
export const collect_collect_api = (data: { type: number; other_id: number }) =>
  request.post("collect/collect", data);


export const game_third_api = () =>
  request.post<GameThirdItem[]>("game/third");


export const game_login_game_api = (data:{gid:number | string ;platform:number}) =>
  request.post<string>("game/login_game",data);