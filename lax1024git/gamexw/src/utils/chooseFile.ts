export default (callBack: (file: File) => void) => {
  const input = document.createElement("input");
  input.type = "file";
  input.addEventListener("change", (event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
      const file = target.files[0];
      callBack(file);
    }
  });
  input.click();
};
