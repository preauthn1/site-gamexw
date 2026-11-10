import PixelImp from "./PixelImp";

class FacebookPixel implements PixelImp {
  constructor(id: string) {
    this.init(id);
  }
  private init(id: string) {
    const script = document.createElement("script");
    script.innerHTML = `!function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments);};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s);}(window, document,"script",
      "https://connect.facebook.net/en_US/fbevents.js");
    this.fbq = window.fbq;
    this.fbq&&this.fbq("init", "${id}");
    this.fbq&&this.fbq("track", "PageView");`;
    document.getElementsByTagName("head")[0].appendChild(script);
  }
  register() {
    window.fbq && window.fbq("track", "CompleteRegistration");
  }
  recharge() {}
  initialCharge(data: { price: string; currency: string }) {
    window.fbq &&
      window.fbq("track", "Purchase", {
        currency: data.currency,
        value: data.price,
      });
  }
}
export default FacebookPixel;
