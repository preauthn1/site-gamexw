export default async (callback: (text: string) => void) => {
  const pastedText = await navigator.clipboard.readText();
  callback(pastedText);
};
