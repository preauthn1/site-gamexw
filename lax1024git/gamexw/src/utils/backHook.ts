class BackHook {
  hook: null | (() => boolean) = null;
  static instance = new BackHook();
  add(hookFuc: () => boolean) {
    this.hook = () => {
      // 执行一次之后清理函数
      this.hook = null;
      return hookFuc();
    };
  }
  remove() {
    this.hook = null;
  }
  static getBackHook() {
    return this.instance;
  }
}
export default BackHook.getBackHook();
