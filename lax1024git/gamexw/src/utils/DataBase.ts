export type DataType<T = unknown> = {
  id: string | number;
  data: T;
};
class DataBase {
  private dbName: string;
  private version: number;
  private indexedDB?: IDBDatabase;
  private tableNames: string[];
  constructor(data: { dbName: string; version: number; tableNames: string[] }) {
    this.dbName = data.dbName;
    this.version = data.version;
    this.tableNames = data.tableNames;
  }

  // 创建表
  createTabel() {
    this.tableNames.forEach((name) => {
      if (!this.indexedDB) return;
      if (!this.indexedDB.objectStoreNames.contains(name)) {
        this.indexedDB.createObjectStore(name, {
          keyPath: "id",
        });
      }
    });
  }

  // 启动数据库
  open() {
    return new Promise((reslove, reject) => {
      const request = window.indexedDB.open(this.dbName, this.version);
      request.onsuccess = (event) => {
        const dbRequest = event.target as IDBOpenDBRequest;
        this.indexedDB = dbRequest?.result;
        reslove(undefined);
      };
      request.onerror = () => {
        reject("数据库打开报错");
      };
      request.onupgradeneeded = (event) => {
        const dbRequest = event.target as IDBOpenDBRequest;
        // 数据库创建或升级的时候会触发
        this.indexedDB = dbRequest.result; // 数据库对象
        this.createTabel();
      };
    });
  }

  // 添加数据
  insert(storeName: string, data: DataType) {
    const { indexedDB } = this;
    if (!indexedDB) throw new Error("数据库未初始化");
    return new Promise((resolve, reject) => {
      const request = indexedDB
        .transaction([storeName], "readwrite") // 事务对象 指定表格名称和操作模式（"只读"或"读写"）
        .objectStore(storeName) // 仓库对象
        .put(data);
      if (request) {
        request.onsuccess = () => {
          resolve(undefined);
        };
        request.onerror = () => {
          reject("数据写入失败");
        };
      }
    });
  }

  // 根据id获取数据
  select<T>(storeName: string, key: DataType["id"]): Promise<T | undefined> {
    const { indexedDB } = this;
    if (!indexedDB) throw new Error("数据库未初始化");
    return new Promise((reslove, reject) => {
      const transaction = indexedDB.transaction([storeName]); // 事务
      const objectStore = transaction.objectStore(storeName); // 仓库对象
      const request = objectStore.get(key);
      request.onerror = () => {
        reject("事务失败");
      };
      request.onsuccess = () => {
        reslove(request.result?.data as T);
      };
    });
  }

  // 根据id获取数据
  selectAll(storeName: string): Promise<DataType[]> {
    const { indexedDB } = this;
    if (!indexedDB) throw new Error("数据库未初始化");
    return new Promise((reslove, reject) => {
      const transaction = indexedDB.transaction([storeName]); // 事务
      const objectStore = transaction.objectStore(storeName); // 仓库对象
      const request = objectStore.getAll();
      request.onerror = () => {
        reject("事务失败");
      };
      request.onsuccess = () => {
        reslove(request.result as DataType[]);
      };
    });
  }

  // 根据id删除数据
  delete(storeName: string, id: DataType["id"]) {
    const { indexedDB } = this;
    if (!indexedDB) throw new Error("数据库未初始化");
    return new Promise((resolve, reject) => {
      const request = indexedDB
        .transaction([storeName], "readwrite")
        .objectStore(storeName)
        .delete(id);
      request.onsuccess = function () {
        resolve(undefined);
      };
      request.onerror = function () {
        reject("数据删除失败");
      };
    });
  }
}

export default DataBase;
