/* eslint-disable indent */
import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import eslintPlugin from "vite-plugin-eslint";
import path from "path";
import AutoImport from "unplugin-auto-import/vite";
import Components from "unplugin-vue-components/vite";
import { ElementPlusResolver } from "unplugin-vue-components/resolvers";
import { lessPluginSetCurrentName } from "./vite_plugins/lessPluginSetCurrentName";
import { createSvgIconsPlugin } from "vite-plugin-svg-icons";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    lessPluginSetCurrentName(),
    AutoImport({
      resolvers: [ElementPlusResolver()],
    }),
    Components({
      resolvers: [ElementPlusResolver()],
    }),
    createSvgIconsPlugin({
      iconDirs: [
        path.resolve(process.cwd(), "src/assets/icon"), //基础图标库
      ],
      symbolId: "icon-[name]",
    }),
    eslintPlugin({
      include: [
        "src/**/*.ts",
        "src/**/*.js",
        "src/**/*.vue",
        "src/*.ts",
        "src/*.js",
        "src/*.vue",
      ],
    }),
  ],
  css: {
    preprocessorOptions: {
      less: {
        modifyVars: {
          hack: `
            true; 
            @import (reference) "${path.resolve(
              __dirname,
              "src/assets/css/media.less"
            )}";
            @import (reference) "${path.resolve(
              __dirname,
              "src/assets/css/common.less"
            )}";
          `,
        },
        javascriptEnabled: true,
      },
    },
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
});
