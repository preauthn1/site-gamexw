const getViewportHeight = () => {
  const vh = Math.max(
    document.documentElement.clientHeight,
    window.innerHeight || 0
  );
  document.documentElement.style.setProperty("--vh", vh + "px");
};
window.addEventListener("resize", getViewportHeight);
window.addEventListener("load", getViewportHeight);
