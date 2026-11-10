import { ref, Ref } from "vue";
import BR from "./BR.json";
const langData = {
  BR,
};
type LangData = Record<string, Record<string, string>>;
class Lang {
  private lang: Ref<LangData | null> = ref(null);
  public locale?: string;
  constructor() {}
  setLang(data: LangData) {
    this.lang.value = data;
  }
  setLocale(locale: string, reload: boolean = false) {
    localStorage.setItem("locale", locale);
    this.locale = locale;
    reload && location.reload();
  }
  getLocale() {
    return this.locale;
  }
  t(key: string, data?: Record<string, string | number>): string {
    if (!this.lang.value) throw new Error("请先格式化语言包");
    if (!this.locale) throw new Error("请先设置默认语言locale");
    let text = this.lang.value[this.locale][key];

    if (!text) {
      text = langData[this.locale]?.[key] || key;
    }

    if (data) {
      for (const key in data) {
        text = text.replace(new RegExp(`{${key}}`, "g"), data[key].toString());
      }
      return text;
    }
    return text;
  }
}
export default new Lang();
