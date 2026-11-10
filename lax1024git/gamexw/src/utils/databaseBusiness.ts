// 数据库业务代码
import DataBase from "./DataBase";
// api 存放api临时数据的表
// 更改表结构记得版本升级
// 如果原来的数据不需要，可以直接更改数据库名字进行发布
export const dataBase = new DataBase({
  dbName: "jslDateBase",
  version: 1,
  tableNames: ["storage", "quick"],
});
