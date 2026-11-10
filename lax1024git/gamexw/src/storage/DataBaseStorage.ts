import { dataBase } from "@/utils/databaseBusiness";
import StorageImp from "./StorageImp";
import { DataType } from "@/utils/DataBase";
class DataBaseStorage<T> implements StorageImp<T> {
  name: string;
  tableName: string = "storage";
  private static data: DataType<unknown>[] = [];
  // quick 快速查询表，会吧所有的存储取出来放到内存中，注意使用，不要存放过多的数据
  private static tableName: string = "quick";
  constructor(name: string) {
    this.name = name;
  }
  setTableName(name: string) {
    this.tableName = name;
  }
  async setData(data: T) {
    if (this.tableName === DataBaseStorage.tableName) {
      DataBaseStorage.setDataBaseStorageData({ id: this.name, data });
    }
    await dataBase.insert(this.tableName, { id: this.name, data });
  }
  async getData() {
    if (this.tableName === DataBaseStorage.tableName) {
      const data = DataBaseStorage.data.find(
        (item) => item.id === this.name
      )?.data;
      return data as T | undefined;
    }
    const data = await dataBase.select<T>("storage", this.name);
    return data as T | undefined;
  }
  private static setDataBaseStorageData(data: DataType) {
    const item = DataBaseStorage.data.find((item) => item.id === data.id);
    if (item) {
      item.data = data.data;
    } else {
      DataBaseStorage.data.push(data);
    }
  }
  async removeData() {
    await dataBase.delete(this.tableName, this.name);
  }
  static async init() {
    this.data = await dataBase.selectAll(DataBaseStorage.tableName);
  }
}
export default DataBaseStorage;
