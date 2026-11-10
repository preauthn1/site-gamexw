<template>
  <!-- sample template -->
  <div id="cricket-page-wrapper" class="sport-page-wrapper">
    <div id="loading">
      <img
        src="https://sprodm.uni247.xyz/images-v3/favicon/android-chrome-192x192.png"
        alt="logo"
      />
      Loading...
    </div>
    <div class="sport-widgets">
      <div
        id="sport-root"
        data-ck-props='{"theme":{"pbgc":"000","sbgc":"1a1a1a","pc":"D2B79E","sc":"333","tc":"333","tpc":"fff","tsc":"999","qlbc":"333","primaryColorLinearGradientParams":{"deg":"","color1":"F4E7D6","opacity1":"","color2":"D2B79E","opacity2":"","color3":"","opacity3":""},"quickLink":{"sponsor":true},"burger":false,"eventList":{"statementLink":true}},"displayHeader":true,"autoBetSuffix":"<https://sprodm.uni247.xyz/>"}'
      ></div>
    </div>
  </div>
</template>
<script setup>
import { onMounted } from "vue";

onMounted(() => {
  if (window.location.hash === "") window.location.hash = "#/";
  // widget loader
  (function (a, b, c, d, e, f, g, h, i) {
    return (
      a[e] ||
      // eslint-disable-next-line @typescript-eslint/no-unused-vars
      ((i = a[e] =
        function () {
          (a[e].q = a[e].q || []).push(arguments);
        }),
      (g = b.createElement(c)),
      (h = b.getElementsByTagName(c)[0]),
      (g.async = 1),
      (g.src = d),
      h.parentNode.insertBefore(g, h))
    );
  })(
    window,
    document,
    "script",
    `https://sprodm.uni247.xyz/international/international-pro.js?v="${Date.now()}"`,
    "CRIC"
  );
  // 此處帶入已取得遊客權杖(token)，後續更換一般權杖也使用此函數
  window.CRIC({
    token: null,
  });
});
</script>

<style scoped>
/* <!-- widget container style --> */
.app .app-home,
body {
  background: #111;
  background-image: initial;
  background-position: initial;
  background-size: initial;
  background-repeat: initial;
  background-attachment: initial;
  background-origin: initial;
  background-clip: initial;
  background-color: rgb(17, 17, 17);
}

body,
html {
  text-size-adjust: none;
  margin: 0;
}

.sport-page-wrapper {
  background-color: rgb(0, 0, 0);
  height: 100vh;
  position: absolute;
  width: 100%;
  z-index: 5;
}

.sport-page-wrapper .sport-widgets {
  bottom: 0;
  left: 0;
  overflow: auto;
  padding-bottom: 0;
  position: fixed;
  right: 0;
  top: 0;
}

#loading {
  position: absolute;
  top: 0;
  left: 0;

  width: 100%;
  height: 100vh;
  background-color: #000;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 50px;
  color: #fff;
  font-family: Avenir Next;
  flex-direction: column;
}
</style>
