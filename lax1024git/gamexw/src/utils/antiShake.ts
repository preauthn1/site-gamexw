// 防抖函数
export default (callBack: () => void, delay = 1000) => {
  let t: number;
  return () => {
    if (t !== null) clearTimeout(t);
    t = setTimeout(() => {
      callBack();
    }, delay);
  };
};
