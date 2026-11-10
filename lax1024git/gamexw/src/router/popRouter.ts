import { ref, Ref } from "vue";
import bus from "@/utils/bus";
import routers from "@/router/pop";
import { ElementType } from "@/types/utils";
// 页面上需要展示的弹窗路由，是个数组，可展示多个
export type ComponentItem = ElementType<typeof routers> & { data?: Record<string, unknown> }
export const componentsActive: Ref<ComponentItem[]> = ref([]);


export const popRouterInit = ()=>{
  //利用全局通信查找需要添加的弹窗页面 
  routers.forEach(item => {
    bus.on(item.name, (data) => {
    // 寻找组件添加到页面
      const componentItem: ComponentItem | undefined = routers.find(componentItem => componentItem.name == item.name);
      if (componentItem) {
        componentItem.data = data as Record<string, unknown>;
        componentsActive.value.push(componentItem);

        // 数组去重，避免重复弹窗
        componentsActive.value = [...new Set(componentsActive.value)];
      }
    });
  });
};
