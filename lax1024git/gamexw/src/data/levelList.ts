export const levelList = [
  { name: "一级", value: "1" },
  { name: "二级", value: "2" },
  { name: "三级", value: "3" },
  { name: "所有", value: "4" },
];
export const findLevel = (value: string) =>
  levelList.find((item) => item.value === value);
