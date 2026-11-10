import { Request } from "./request";
import SystemChannelType from "@/enum/SystemChannelType";
export type SystemInfoApi = Request<{
  android_download_url: string;
  app_version: string;
  bind_card_status: number;
  code_check: number;
  currency: {
    code: string;
    symbol: string;
    symbol_img: string;
  };
  detail_notes: { value: string }[];
  favicon: string;
  flyerStatus: number;
  force_pwd: number;
  ios_download_url: string;
  is_agent_reward: number;
  is_reg_type: number;
  mail_phone_ip_num: {
    mail_ip_num: number;
    mail_open: number;
    mail_time: number;
    phone_ip_num: number;
    phone_open: number;
    phone_time: number;
  };
  mobile_service_url: string;
  pc_service_url: string;
  phone_bind: number;
  phone_check: number;
  promotion_code: number;
  quick_recharge: string[];
  quick_withdrawal: string[];
  register_first_account_type: string;
  revoke_order: number;
  saba_check: number;
  service_charge: number;
  shabaStatus: number;
  start_page_status: number;
  test_withdraw: number;
  thirdLoginStatus: string;
  transaction_charge: number;
  turntableStatus: string;
  update_wpassword: number;
  usdt_quick_recharge: string[];
  usdt_quick_withdrawal: string[];
  user_google_status: number;
  web_name: string;
  web_url: string;
  withdrawal_check_type: number;
  withdrawal_pwd_check: number;
  withdrawal_status: number;
  tg_condition: number;
  tg_condition_valid_bet: string;
}> & {
  alllang: {
    icon: string;
    label: string;
    value: string;
  }[];
  idc: {
    label: string;
    value: string;
  }[];
  idc_default: string;
  lang: Record<string, Record<string, string>>;
  locale: string;
  locale_flag: string;
  nation: {
    flag: string;
    is_defa: number;
    langs: string[];
    name: string;
  }[];
  time_zone: string;
};

export type SystemInfoNewApi = Request<[]> & {
  lang: SystemInfoApi.lang;
  site: {
    website_name: string;
    website_web_url: string;
    website_favicon: string;
    website_pc_logo: string;
    website_desktop_logo: string;
    website_mobile_logo: string;
  };
  banner: BannerIndexApi;
};

export type BannerItem = {
  img: string;
  jump_url: string;
  lang: string;
  mobile_img: string;
  show_position: number;
  sort: number;
  type: number;
  url: string;
};
export type BannerIndexApi = {
  mobile: BannerItem[];
  pc: BannerItem[];
};

export type LoginApi = {
  token: string;
  token_key: string;
};

export type NoticeListApiItem = {
  add_time: string;
  content: string;
  id: number;
  is_show_time: number;
  jump_url: string;
  show_type: number;
  title: string;
};

export type IndexMenuItem = {
  children?: IndexMenuItem[];
  extra: string;
  href: string;
  icon: string;
  icon_url: string;
  is_img: number;
  name: string;
};
export type IndexMenu = {
  float_menu: IndexMenuItem[];
  footer_menu: IndexMenuItem[];
  index_about: IndexMenuItem[];
  index_help: IndexMenuItem[];
  index_icon: IndexMenuItem[];
  index_rule: IndexMenuItem[];
  left_menu: IndexMenuItem[];
  left_menu_casino: IndexMenuItem[];
  left_menu_join: IndexMenuItem[];
  left_menu_support: IndexMenuItem[];
  user_center_menu: IndexMenuItem[];
  wg_service: IndexMenuItem[];
  wg_footer_service: IndexMenuItem[];
  xw_mobile_game_menu: IndexMenuItem[];
  xw_mobile_navbar: IndexMenuItem[];
  xw_pc_footer_menu: IndexMenuItem[];
  xw_pc_middle_menu: IndexMenuItem[];
  xw_pc_middle_pic_menu: IndexMenuItem[];
  xw_pc_site_menu: IndexMenuItem[];
  xw_pc_top_menu: IndexMenuItem[];
};

export type Rule = {
  content: string;
  title: string;
};

export type SystemChannel = {
  code: string;
  type: SystemChannelType;
  share_code: string;
};
