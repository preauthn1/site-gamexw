export default (num?: number | string, fixedNum = 2) => {
  num = Number(num) || 0;
  const options = {
    minimumFractionDigits: fixedNum,
    maximumFractionDigits: fixedNum,
    useGrouping: true, // 使用分隔符
  };
  const loc = "en-US";
  return new Intl.NumberFormat(loc, options).format(num);
};
