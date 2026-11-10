export const require = (
  imgPath: string,
  isImg = true,
  isModel: boolean = false
) => {
  if (isImg) {
    // 判断是否区分项目来引入图片
    // 先去寻找项目分开里面的图片，如果没有，再到images下面去寻找
    if (isModel) {
      const href = new URL(
        `../assets/images-${
          import.meta.env.VITE_THEME_IMG || import.meta.env.VITE_THEME
        }/${imgPath}`,
        import.meta.url
      ).href;
      if (!href.includes("undefined")) {
        return href;
      }
    }
    return new URL(`../assets/images/${imgPath}`, import.meta.url).href;
  } else {
    return new URL(`../${imgPath}`, import.meta.url).href;
  }
};
export type Require = typeof require;
