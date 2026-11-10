import useStore from "@/store";
import BaseStorage from "./BaseStorage";
type TokenType = { token: string; token_key: string };
const tokenStorage = new BaseStorage<TokenType>("token", true);

class Token {
  public static setData(data: TokenType) {
    useStore().userStore.changeLogin(true);
    tokenStorage.setData(data);
  }
  public static getData() {
    return tokenStorage.getData();
  }
  public static removeData() {
    tokenStorage.removeData();
    useStore().userStore.changeLogin(false);
  }
}

export default Token;
