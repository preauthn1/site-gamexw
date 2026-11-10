// 无线滚动指令
export const loadBottomMore = {
  mounted: (el: HTMLElement, data) => {
    if (!data.value) return;
    let isLoad = false;
    el.addEventListener("scroll", () => {
      if (el.scrollHeight - el.scrollTop - el.clientHeight < 10) {
        if (!isLoad) {
          data.value();
        }
        isLoad = true;
      } else {
        isLoad = false;
      }
    });
  },
};
