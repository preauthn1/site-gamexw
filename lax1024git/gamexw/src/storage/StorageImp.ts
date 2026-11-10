abstract class StorageImp<T> {
  abstract name?: string;
  abstract setData(data: T): void;
  abstract getData(): T | undefined | Promise<T | undefined>;
  abstract removeData(): void;
}
export default StorageImp;
