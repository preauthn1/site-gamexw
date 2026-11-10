import { createRouter, createWebHashHistory } from "vue-router";
import routes from "./routes";
import pop from "./pop";
import useStore from "@/store";
import { OpenState } from "@/enum/OpenState";
import { openLink } from "@/utils/openLink";
import bus from "@/utils/bus";


const router = createRouter({
  history: createWebHashHistory(),
  scrollBehavior: (to, from, savedPosition) => {
    if (savedPosition) {
      return savedPosition;
    } else if (to.path !== from.path) {
      return { top: 0 };
    }
  },
  routes,
});

router.beforeEach((to, form, next) => {
  const { userStore } = useStore();
  
  // 弹窗路由逻辑
  const queryData = to.query.data as string | undefined;
  const toPopItem = pop.find((item) => item.path === to.path);

  // 登录验证
  if (!to.meta.noLogin && !userStore.isLogin && !toPopItem?.meta?.noLogin) {
    openLink("/login");
    return;
  }

  if (toPopItem) {
    // 由于query不支持函数传递，弹窗跳转尽量用封装的openlink函数
    bus.emit(toPopItem.name, queryData && JSON.parse(queryData));
    router.replace({
      path: form.path,
      query: to.query,
    });
    return;
  }

  // 未设置提现密码
  if (
    to.path === "/withdraw" &&
    userStore.userInfo?.pay_passwd_verify === OpenState.open &&
    !userStore.userInfo.withdrawal_psd
  ) {
    openLink("/confirm", {
      content:
        "检测到您的账号资料尚未完善，为保证您的资金账户安全，请完善您提现密码！",
      btnConfirmText: "去设置",
      confirm: () => {
        router.push("/withdrawalPass");
      },
    });
    return;
  }
  next();
});

export default router;
