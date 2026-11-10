export type WebSocketData = {
  host?: string;
  port?: number;
  order: string;
  mode: string;
  title: string;
  msg: string;
  showtime: string;
  time: string;
};

export type WebSocketRechargeContent = {
  msg: string;
  current_symbol: string;
  amount: string;
};

export type WebSocketActivityContent = {
  msg: string;
  current_symbol: string;
  amount: string;
};

export type WebSocketTaskContent = {
  type:
    | "task"
    | "sign"
    | "action"
    | "pdd"
    | "invite"
    | "vip_week"
    | "vip_month";
  finish_times: string;
};
