import OffLine from "@/components/common/OffLine.vue";
import ChangeAvatar from "@/components/common/ChangeAvatar.vue";
import Deposit from "@/components/common/Deposit.vue";
import DepositRecords from "@/components/common/DepositRecords.vue";
import InterestDeposit from "@/components/common/InterestDeposit.vue";
import Login from "@/components/common/Login.vue";
import Music from "@/components/common/Music.vue";
import Confirm from "@/components/common/Confirm.vue";
import RedemptionCode from "@/components/common/RedemptionCode.vue";
import Register from "@/components/common/Register.vue";
import RetrievePassword from "@/components/common/RetrievePassword.vue";
import Rule from "@/components/common/Rule.vue";
import SendCodePop from "@/components/common/SendCodePop.vue";
import TeamUser from "@/components/common/TeamUser.vue";
import QRCodePayment from "@/components/common/QRCodePayment.vue";
import AddPIX from "@/components/common/AddPIX.vue";
import AddBank from "@/components/common/AddBank.vue";
import AddUsdt from "@/components/common/AddUsdt.vue";
import AddElectronicWallet from "@/components/common/AddElectronicWallet.vue";
import ChangeLangSelectPop from "@/components/common/ChangeLangSelectPop.vue";
const routes = [
  {
    path: "/login",
    name: "login",
    component: Login,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/register",
    name: "register",
    component: Register,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/rule",
    name: "rule",
    component: Rule,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/changeAvatar",
    name: "changeAvatar",
    component: ChangeAvatar,
  },
  {
    path: "/music",
    name: "music",
    component: Music,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/deposit",
    name: "deposit",
    component: Deposit,
  },
  {
    path: "/offLine",
    name: "offLine",
    component: OffLine,
  },
  {
    path: "/qrCodePayment",
    name: "qrCodePayment",
    component: QRCodePayment,
  },
  {
    path: "/depositRecords",
    name: "depositRecords",
    component: DepositRecords,
  },
  {
    path: "/interestDeposit",
    name: "interestDeposit",
    component: InterestDeposit,
  },
  {
    path: "/redemptionCode",
    name: "redemptionCode",
    component: RedemptionCode,
  },
  {
    path: "/retrievePassword",
    name: "retrievePassword",
    component: RetrievePassword,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/confirm",
    name: "confirm",
    component: Confirm,
    meta: {
      noLogin: true,
    },
  },
  {
    path: "/sendCodePop",
    name: "sendCodePop",
    component: SendCodePop,
  },
  {
    path: "/teamUser",
    name: "teamUser",
    component: TeamUser,
  },
  {
    path: "/addPIX",
    name: "addPIX",
    component: AddPIX,
  },
  {
    path: "/addBank",
    name: "addBank",
    component: AddBank,
  },
  {
    path: "/addUsdt",
    name: "addUsdt",
    component: AddUsdt,
  },
  {
    path: "/addElectronicWallet",
    name: "addElectronicWallet",
    component: AddElectronicWallet,
  },
  {
    path: "/changeLangSelectPop",
    name: "changeLangSelectPop",
    component: ChangeLangSelectPop,
    meta: {
      noLogin: true,
    },
  },
];
export default routes;
