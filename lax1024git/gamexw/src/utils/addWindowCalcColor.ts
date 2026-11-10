function calcColor(color1:string,color2:string){
  function hexToRgb(hex) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return { r, g, b };
  }
  const color1Rgb =  hexToRgb(color1);
  const color2Rgb =  hexToRgb(color2);
  return [
    color1Rgb.r-color2Rgb.r, 
    color1Rgb.g-color2Rgb.g, 
    color1Rgb.b-color2Rgb.b
  ];
}
window.calcColor = calcColor;