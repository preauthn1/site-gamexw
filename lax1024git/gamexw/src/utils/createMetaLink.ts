export default (rel: string, href: string) => {
  const links = [...document.getElementsByTagName("link")];
  const el = links.find((item) => item.rel == rel);
  if (el) {
    el.href = href;
    return;
  }
  const link = document.createElement("link");
  link.rel = rel;
  link.href = href;
  document.getElementsByTagName("head")[0].appendChild(link);
};
