import {
  WebSocketActivityContent,
  WebSocketRechargeContent,
} from "@/types/api/webSocketData";
import { ElNotification } from "element-plus";
import numInit from "@/utils/numInit";
import { openLink } from "@/utils/openLink";
import lang from "@/lang";
import useStore from "@/store";
import { wsServe } from "./webSocket";

export default () => {
  const { userStore } = useStore();

  // 注册充值事件监听
  wsServe.onMessage<WebSocketRechargeContent>({ mode: "recharge" }, (data) => {
    ElNotification({
      title: lang.t(data.title),
      message:
        lang.t(data.content.msg) +
        data.content.current_symbol +
        numInit(data.content.amount),
      duration: Number(data.showtime) * 1000 || 2000,
    });
    userStore.getUserInfo();
  });

  // 注册活动事件监听
  wsServe.onMessage<WebSocketActivityContent>({ mode: "activity" }, (data) => {
    ElNotification({
      title: lang.t(data.title),
      message:
        lang.t(data.content.msg) +
        data.content.current_symbol +
        numInit(data.content.amount),
      duration: Number(data.showtime) * 1000 || 2000,
    });
    userStore.getUserInfo();
  });

  // 注册弹窗事件监听
  wsServe.onMessage({ mode: "pop" }, (data) => {
    openLink("/confirm", {
      title: data.title,
      content: data.msg,
    });
  });

  // 注册提现事件监听
  wsServe.onMessage({ mode: "withdraw" }, (data) => {
    ElNotification({
      title: lang.t(data.title),
      message: lang.t(data.msg),
      duration: Number(data.showtime) * 1000 || 2000,
    });
  });
};
