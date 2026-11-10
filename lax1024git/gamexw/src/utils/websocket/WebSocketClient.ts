import WebSocketClientTools from "./WebSocketClientTools";
import querystring from "querystring-es3";
class WebSocketClient {
  private websocket?: WebSocket;
  private url: string = ""; //路径
  private isClose: boolean = false; //是否主动关闭链接，主动关闭禁止重连
  private reconnectInterval: number = 6000; //重连间隔（毫秒）
  private heartbeatInterval: number = 6000; //心跳时间（毫秒）
  private reconnectTimeId: number | null = null;
  private heartbeatTimeId: number | null = null;
  private webSocketClientTools: WebSocketClientTools;
  private sendToken?: () => void;
  public onMessage: WebSocketClientTools["onMessage"];
  public closeMessage: WebSocketClientTools["closeMessage"];

  constructor(url?: string, webSocketClientTools = new WebSocketClientTools()) {
    this.webSocketClientTools = webSocketClientTools;
    this.onMessage = webSocketClientTools.onMessage.bind(webSocketClientTools);
    this.closeMessage =
      webSocketClientTools.closeMessage.bind(webSocketClientTools);
    if (url) {
      this.url = url;
    }
  }
  setUrl(url: string) {
    this.url = url;
  }

  setSendToken(sendToken: () => void) {
    this.sendToken = sendToken;
  }

  // 链接
  connect() {
    this.isClose = false;
    return new Promise<{ code: number }>((reslove) => {
      this.websocket = new WebSocket(this.url);

      this.websocket.onopen = () => {
        reslove({ code: 200 });
        // 链接成功，开启心跳发送
        this.heartbeat();
        // 链接成功，关闭重连
        this.reconnectClose();
        this.sendToken && this.sendToken();
      };

      this.websocket.onmessage = (event) => {
        this.webSocketClientTools.runCallBack(event.data);
      };

      this.websocket.onclose = () => {
        reslove({ code: 0 });

        // 主动关闭的释放掉后面操作
        if (this.isClose) return;
        // 开启重连
        this.reconnect();
        // 关闭心跳发送信息
        this.heartbeatTimeId && clearInterval(this.heartbeatTimeId);
      };

      this.websocket.onerror = () => {
        reslove({ code: 500 });
        // 主动关闭的释放掉后面操作
        if (this.isClose) return;
        // 开启重连
        this.reconnect();
        // 关闭心跳发送信息
        this.heartbeatTimeId && clearInterval(this.heartbeatTimeId);
      };
    });
  }

  // 重连
  private reconnect() {
    this.reconnectTimeId && clearInterval(this.reconnectTimeId);
    this.reconnectTimeId = setInterval(() => {
      console.error("websocket 重连中", this.url);
      this.connect();
    }, this.reconnectInterval);
  }
  private reconnectClose() {
    this.reconnectTimeId && clearInterval(this.reconnectTimeId);
  }

  // 心跳发送
  private heartbeat() {
    this.heartbeatTimeId && clearInterval(this.heartbeatTimeId);
    this.heartbeatTimeId = setInterval(() => {
      this.send({ type: "online" });
    }, this.heartbeatInterval);
  }

  send(message: Record<string, number | string>) {
    if (this.websocket?.readyState === WebSocket.OPEN) {
      this.websocket.send(querystring.stringify(message) as string);
    } else {
      console.error("websocket 未打开，无法发送信息", this.url);
    }
  }

  close() {
    this.reconnectTimeId && clearInterval(this.reconnectTimeId);
    this.heartbeatTimeId && clearInterval(this.heartbeatTimeId);
    this.isClose = true;
    this.websocket?.close();
  }
}
export { WebSocketClient };
