module.exports = {
  plugins: {
    "postcss-pxtorem": {
      rootValue: 100, // 指定转换倍率，我现在设置这个表示1rem=37.5px;
      propList: ["*"], // 属性列表，表示你要把哪些css属性的px转换成rem，这个*表示所有
      selectorBlackList:["html"],
      mediaQuery: false, // 是否允许使用媒体查询，false媒体查询的代码可用，true不可用
      exclude: "ignore",
      replace: true, // 替换包含rem的规则，而不是添加回退
      minPixelValue: 1, // 需要转换的最小值，一般1px像素不转换，以上才转换
      unitPrecision: 6, // 转换成rem单位的小数点后的保留位数
    },
  },
};