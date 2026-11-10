import lang from "@/lang";
import { require } from "./$require";
import { BankType } from "@/enum/BankType";
import { ValueOf } from "@/types/utils";

const data = [
  { name: lang.t("PIX"), icon: require("pix2.png"), type: BankType.pix },
  { name: lang.t("USDT"), icon: require("usdt.png"), type: BankType.usdt },
  { name: lang.t("银行卡"), icon: require("bank.png"), type: BankType.bank },
  {
    name: lang.t("电子钱包"),
    icon: require("electronicWallet.png"),
    type: BankType.electronicWallet,
  },
];
export default (type: ValueOf<typeof BankType>) =>
  data.find((item) => item.type === type);
