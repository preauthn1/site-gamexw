interface PixelImp {
  // 注册
  register: () => void;
  // 充值
  recharge: (data: { price: string; currency: string }) => void;
  // 首次充值
  initialCharge: (data: { price: string; currency: string }) => void;
}
export default PixelImp;
