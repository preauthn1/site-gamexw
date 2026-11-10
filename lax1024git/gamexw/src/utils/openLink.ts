import router from "@/router";
import useStore from "@/store";
import pop from "@/router/pop";
import bus from "@/utils/bus";

// 弹窗跳转
export const openPopupRoute = (url: string, data?: Record<string, unknown>) => {
  const { userStore } = useStore();
  const routerItem = pop.find((item) => item.path === url);
  if (routerItem) {
    if (!routerItem?.meta?.noLogin && !userStore.isLogin) {
      openLink("/login");
    } else {
      // 全局通信传递给组件
      bus.emit(routerItem.name, data);
    }
  }
  return Boolean(routerItem);
};

// 站外跳转
export const openExternalLink = (url: string) => {
  const { systemStore } = useStore();
  const isOpen = url.startsWith("http");
  if (isOpen) {
    if (systemStore.isPhone) {
      location.href = url;
    } else {
      window.open(url);
    }
  }
  return isOpen;
};

// 统一调度
export const openLink = (url: string, data?: Record<string, unknown>) => {
  openExternalLink(url) || openPopupRoute(url, data) || router.push(url);
};

export type OpenLink = typeof openLink;