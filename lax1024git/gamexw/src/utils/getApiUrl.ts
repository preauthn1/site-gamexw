// 使用惰性函数提高性能
const createGetApiUrl = () => {
  const url = import.meta.env.VITE_API_BASE_URL as string;
  if (import.meta.env.VITE_API_BASE_URL_AUTO === "true") {
    return () =>
      import.meta.env.DEV
        ? url
        : window.location.protocol +
          "//api." +
          window.location.host.split(".").slice(-2).join(".");
  } else {
    return () => url;
  }
};
export default createGetApiUrl();
