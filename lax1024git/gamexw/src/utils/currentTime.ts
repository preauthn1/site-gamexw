import { ref } from "vue";

export const currentTime = ref(new Date().getTime());
setInterval(()=>{
  currentTime.value = new Date().getTime();
},1000);