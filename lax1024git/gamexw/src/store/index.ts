import menu from "./modules/menu.ts";
import downloadTip from "./modules/downloadTip.ts";
import system from "./modules/ststem.ts";
import homeActve from "./modules/homeActve.ts";
import user from "./modules/user.ts";
import gameType from "./modules/gameType.ts";
import horseRaceLamp from "./modules/horseRaceLamp.ts";
import music from "./modules/music.ts";
import activity from "./modules/activity.ts";
import bank from "./modules/bank.ts";
import indexMenu from "./modules/indexMenu.ts";
import shareCode from "./modules/shareCode.ts";
import teamRecommend from "./modules/teamRecommend.ts";
import env from "./modules/env.ts";
export default function useStore() {
  return {
    menuStore: menu(),
    envStore: env(),
    downloadTipStore: downloadTip(),
    systemStore: system(),
    homeActveStore: homeActve(),
    userStore: user(),
    gameTypeStore: gameType(),
    horseRaceLampStore: horseRaceLamp(),
    musicStore: music(),
    activityStore: activity(),
    bankStore: bank(),
    indexMenuStore: indexMenu(),
    shareCodeStore: shareCode(),
    teamRecommendStore: teamRecommend(),
  };
}
