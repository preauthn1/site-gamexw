enum Theme {
  blue = "@/assets/css/theme/blue.less",
  red = "@/assets/css/theme/red.less",
  black = "@/assets/css/theme/black.less",
  green = "@/assets/css/theme/green.less",
  cyan = "@/assets/css/theme/cyan.less",
}
if (import.meta.env.VITE_THEME === "blue") {
  import(Theme.blue);
}
if (import.meta.env.VITE_THEME === "red") {
  import(Theme.red);
}
if (import.meta.env.VITE_THEME === "black") {
  import(Theme.black);
}
if (import.meta.env.VITE_THEME === "green") {
  import(Theme.green);
}
if (import.meta.env.VITE_THEME === "cyan") {
  import(Theme.cyan);
}
