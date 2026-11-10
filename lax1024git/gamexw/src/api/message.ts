import { MessageListItem, MessageSystemItem } from "@/types/api/message";
import { request } from "@/utils/request";

export const message_list_api = (data: {
  page: number;
  limit: number;
  unread: number;
}) => request.post<MessageListItem[]>("message/list", data);

export const message_system_msg_api = (data: { page: number; limit: number }) =>
  request.post<MessageSystemItem[]>("message/system_msg", data);

export const message_setread_api = (data: { msg_id: number | string }) =>
  request.post<MessageSystemItem[]>("message/setread", data);
