import CryptoJS from "crypto-js";
class Encryption {
  // 密钥
  private static passphrase =
    "degrehytjhytjkjfioewjgreiogjorejgoirjiogdlmvlksdmbglfdkmbhrghioerjgorejgol";

  // 加密
  static encrypt<T>(data: T, passphrase: string = Encryption.passphrase) {
    const ciphertext = CryptoJS.AES.encrypt(JSON.stringify(data), passphrase);
    return ciphertext.toString();
  }

  // 解密
  static decrypt<T>(
    ciphertext: string,
    passphrase: string = Encryption.passphrase
  ) {
    const bytes = CryptoJS.AES.decrypt(ciphertext, passphrase);
    const originalText = bytes.toString(CryptoJS.enc.Utf8);
    return JSON.parse(originalText) as T;
  }
}
export default Encryption;
