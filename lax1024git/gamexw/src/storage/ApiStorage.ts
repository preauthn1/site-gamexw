// import Storage from "./BaseStorage";
import Storage from "./DataBaseStorage";
import { Request } from "@/types/api/request";
import { ResCode } from "@/enum/ResultCode";
import lang from "@/lang";
import md5 from "md5";
class ApiStorage<T> {
  private storage: Storage<T>;
  api: () => Promise<Request<T>>;
  success: (data: T) => void;
  isNotFetchUpdateAgain?: boolean;

  constructor(data: {
    api: () => Promise<Request<T>>;
    success: (data: T) => void; //成功的回调;
    key?: string;
    isNotFetchUpdateAgain?: boolean; //是否不要再次更新，否，表示二次拉取接口更新数据，true ，表示不更新
  }) {
    this.api = data.api;
    this.success = data.success;
    this.isNotFetchUpdateAgain = data.isNotFetchUpdateAgain;
    this.storage = new Storage<T>(
      md5(this.api.toString() + lang.locale + data.key)
    );
    this.storage.setTableName("quick");
  }

  async getData() {
    const storageData = await this.storage.getData();
    if (storageData) {
      this.success(storageData);
      this.getApiData(this.isNotFetchUpdateAgain, true, storageData);
    } else {
      await this.getApiData(false, true);
    }
  }

  async getApiData(
    isNotFetchUpdateAgain = false,
    isStorage = false,
    storageData?: T
  ) {
    const res = await this.api();
    if (res.code === ResCode.success) {
      if (isStorage) this.storage.setData(res.data);
      // 判断数据变化，才进行二次更新
      if (
        !isNotFetchUpdateAgain &&
        JSON.stringify(storageData) !== JSON.stringify(res.data)
      ) {
        this.success(res.data);
      }
    }
  }
}

export default ApiStorage;
