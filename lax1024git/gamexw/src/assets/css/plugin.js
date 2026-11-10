const calcColor = (color1,color2)=>{
  return [
    color1.rgb[0]-color2.rgb[0], 
    color1.rgb[1]-color2.rgb[1], 
    color1.rgb[2]-color2.rgb[2]
  ];
};

const  limitValue = (number)=>{
  if(number < 0) number = 0;
  if(number > 255) number = 255;
  return number;
};
const calcNum = (num1,num2)=>{
  return limitValue(num1 - num2);

};

// eslint-disable-next-line no-undef
module.exports = {
  install: (less, pluginManager, functions) => {
    functions.add("calcColor", function (themeColor,baseColor,targetColor) {
      const cha = calcColor(baseColor,targetColor);
      return `rgb(${calcNum(themeColor.rgb[0],cha[0])},${calcNum(themeColor.rgb[1],cha[1])},${calcNum(themeColor.rgb[2],cha[2])})`;
    });
  },
};
