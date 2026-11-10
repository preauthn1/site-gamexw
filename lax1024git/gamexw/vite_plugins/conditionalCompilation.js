
class ConditionalCompilation{
  constructor(dev){
    this.instruct = dev;
  }
  transform(code,id){

    try {
      if(this.instruct && !id.includes("node_modules")){
        const codeList = code.match(new RegExp("[\\S]+?.(#ifdef|#ifndef) +[\\s\\S]+?.#endif.*[\\S]*","g"));
        if(codeList){
          codeList.forEach(item => {
            // 取出第一行的字符串进行识别验证
            const devLineOne = item.split("\r\n")[0];
            const ifdef = devLineOne.includes("#ifdef");
            // 替换#ifdef
            if(ifdef){
              const devs = devLineOne.split("#ifdef")[1].split("||");
              // 找不到环境替换成空的字符串
              const flag = !devs.find(item=>item.includes(this.instruct));
              // 替换字符串
              if(flag){
                code = code.replace(item,"");
              }
            }else{
              // 替换#ifndef
              const devs = devLineOne.split("#ifndef")[1].split("||");
              // 找到环境替换成空的字符串
              const flag = devs.find(item=>item.includes(this.instruct));
              if(flag){
                code = code.replace(item,"");
              }
            }
          });
        }
        return code;
      }
    } catch (error) {
      return code;
    }
    
  }
}


export default config=>{
  const conditionalCompilation = new ConditionalCompilation(config?.dev);
  return {
    name:"vite-conditional-compilation",
    enforce:"pre",
    transform:conditionalCompilation.transform.bind(conditionalCompilation)
  };
};