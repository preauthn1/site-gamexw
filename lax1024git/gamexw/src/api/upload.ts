import { Request } from "@/utils/request";
const request = new Request({
  headers: {},
});

export const upload_api = (file: File) => {
  const formData = new FormData();
  formData.append("file", file);
  return request.post<{ file: string }>("upload/img", formData);
};
