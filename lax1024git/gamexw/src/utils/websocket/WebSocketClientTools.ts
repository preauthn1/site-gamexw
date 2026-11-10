import { WebSocketData } from "@/types/api/webSocketData";
import querystring from "querystring-es3";

export type WebSocketClientToolsRouter = { order?: string; mode?: string };
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type CallBack<T = any> = (
  webSocketData: WebSocketData & { content: T }
) => void;

class WebSocketClientTools {
  // 消息路由存储空间
  private messageList: {
    callBack: CallBack;
    router: WebSocketClientToolsRouter;
  }[] = [];

  // 注册
  onMessage<T = undefined>(
    router: WebSocketClientToolsRouter,
    callBack: CallBack<T>
  ) {
    this.messageList.push({
      router,
      callBack,
    });
  }

  // 销毁
  closeMessage(callBack: () => void) {
    this.messageList = this.messageList.filter(
      (item) => item.callBack !== callBack
    );
  }

  runCallBack(data: string) {
    const webSocketData = querystring.parse(data) as WebSocketData;
    this.messageList.forEach((item) => {
      if (
        item.router.mode === webSocketData.mode ||
        item.router.order === webSocketData.order
      ) {
        // 需要从msg里面解析content
        try {
          item.callBack({
            ...webSocketData,
            content: webSocketData.msg && JSON.parse(webSocketData.msg),
          });
        } catch (error) {
          item.callBack({
            ...webSocketData,
            content: undefined,
          });
        }
      }
    });
  }
}

export default WebSocketClientTools;
