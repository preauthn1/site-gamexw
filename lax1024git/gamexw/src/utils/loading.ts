import { ElLoading } from "element-plus";
import { LoadingInstance } from "element-plus/es/components/loading/src/loading";

class Loading {
  static loadingInstance: LoadingInstance | null = null;
  static show() {
    if (this.loadingInstance) this.hide();
    this.loadingInstance = ElLoading.service({
      lock: true,
      background: "rgba(0, 0, 0, 0.3)",
    });
  }
  static hide() {
    this.loadingInstance?.close();
    this.loadingInstance = null;
  }
}
export default Loading;
