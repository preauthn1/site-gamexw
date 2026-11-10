import { user_get_ws_token_api } from "@/api/user";
import { ResCode } from "@/enum/ResultCode";
import { WebSocketClient } from "@/utils/websocket/WebSocketClient";
import { WebSocketOrder } from "@/enum/WebSocketOrder";

// WebSocket 业务代码，注册比较繁琐
// 流程：
// 1：首先进行接口拉取，获取token，和询问的Socket地址
// 2：进行Socket询问连接，获取真正的Socket服务器地址
// 3：获取地址成功，进行连接，链接成功需要传输，token，uid等进行登录
// 4：Socket重连的时候需要重新拉取token，否则会一直挂掉，网络流程走下来，登录成功，正常网络测试三秒左右
// 5：注意：第一次链接的服务需要主动注销掉，不注销会进行二次重新连接，不要做缓存优化，token都是一次性的

// 长连接对象，可以注销注册事件，对外暴露，用作全局对象处理, 对外暴露的初始化必须有对象，方便外部函数注册事件使用
export const wsServe: WebSocketClient = new WebSocketClient();

// 初始化函数
export const init = async () => {
  const res = await user_get_ws_token_api();
  if (res.code === ResCode.success && res.data.WebSocket) {
    // 首先链接，获取二次链接地址
    const ws = new WebSocketClient(res.data.WebSocket);
    await ws.connect();
    ws.send({ type: "askserver" });
    ws.onMessage({ order: WebSocketOrder.server }, async (data) => {
      ws.close();
      // 获取地址，再次进行连接，原来的链接关闭
      const url = `wss://${data.host}/ws${data.port}`;
      await connect({ url, token: res.data.token, uid: res.data.uid });
    });
  }
};

// 正式链接，调用此函数
const connect = async (data: { url: string; token: string; uid: string }) => {
  wsServe.setUrl(data.url);
  // token 只能用一次，用完之后要重新拉取接口，才能登录成功
  let { uid, token } = data;

  // 链接成功，或重新连接成功，会触发此函数
  wsServe.setSendToken(async () => {
    // token 不存在，重新拉取token
    if (!token) {
      const res = await user_get_ws_token_api();
      if (res.code === ResCode.success) {
        uid = res.data.uid;
        token = res.data.token;
      }
    }
    // 发送token，进行登录请求
    wsServe?.send({ type: "add", uid, token });
    // 使用完后，置空token，下次使用，重新拉取接口
    token = "";
  });
  // 开始链接
  await wsServe.connect();
};
