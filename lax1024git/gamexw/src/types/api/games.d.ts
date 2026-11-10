import { Request } from "./request";
export type GameTypeItem = {
  game_count?: number;
  icon: string;
  default_icon: string;
  id?: number | string;
  name: string;
  sort?: number;
  tag?: string;
};
export type GameType = Request<GameTypeItem[]> & {
  tags: string[];
};

export type GameItem = {
  g_exfield: string;
  g_hot: number;
  g_is_new: number;
  g_is_optimum: number;
  g_is_self: number;
  g_tcid: number;
  g_type: number;
  game_name: string;
  game_status: number;
  gi_content: string;
  gi_headimg: string;
  gi_info: string;
  icon: string;
  id: number;
  is_collect: number;
  tc_code: string;
  tc_name: string;
};

export type GameThirdItem = {
  game_number: number;
  icon: string;
  simple_icon: string;
  sort: number;
  tc_id: number;
  tc_name: string;
};
