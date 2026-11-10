import { ElMessage } from "element-plus";
import lang from "../lang";
export default (val:string = "") => {
  const input = document.createElement("input"); //创建input 
  input.setAttribute("value", val);            //把input设置value
  document.body.appendChild(input);            //添加这个dom对象
  input.select();                              //选中该输入框
  document.execCommand("copy");                //复制该文本 
  document.body.removeChild(input);            //移除输入框
  ElMessage({
    type:"success",
    message:lang.t("复制成功")
  });
};