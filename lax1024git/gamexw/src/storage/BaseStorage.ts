import StorageImp from "./StorageImp";

class BaseStorage<T> implements StorageImp<T> {
  name: string;
  isSerialize: boolean;
  constructor(name: string, isSerialize: boolean = false) {
    this.name = name;
    this.isSerialize = isSerialize;
  }
  setData(data: T): void {
    if (this.isSerialize) {
      localStorage.setItem(this.name, JSON.stringify(data));
    } else {
      localStorage.setItem(this.name, data as string);
    }
  }
  getData() {
    const localStorageData = localStorage.getItem(this.name);
    if (!localStorageData) return;
    if (this.isSerialize) {
      return JSON.parse(localStorageData) as T;
    } else {
      return localStorageData as T;
    }
  }
  removeData(): void {
    localStorage.removeItem(this.name);
  }
}

export default BaseStorage;
