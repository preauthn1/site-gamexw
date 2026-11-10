import { h, render, ref } from "vue";
import Alert from "./LaunchPage.vue";
const isShow = ref(false);
const container = document.createElement("div");
const vm = h(Alert, { isShow: isShow });
render(vm, container);
document.body.appendChild(container);
export default {
  show: () => {
    isShow.value = true;
  },
  hide: () => {
    isShow.value = false;
  },
};
