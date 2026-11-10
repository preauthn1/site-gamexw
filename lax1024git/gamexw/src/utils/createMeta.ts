export default (name: string, content: string) => {
  const metas = [...document.getElementsByTagName("meta")];
  const el = metas.find((item) => item.name == name);
  if (el) {
    el.content = content;
    return;
  }
  const meta = document.createElement("meta");
  meta.name = name;
  meta.content = content;
  document.getElementsByTagName("head")[0].appendChild(meta);
};
