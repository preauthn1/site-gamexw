import { Plugin } from "vite";
export const lessPluginSetCurrentName = ():Plugin =>{
  return {
    name:"lessPluginSetCurrentName",
    enforce:"pre",
    transform:()=>{}
  };
};