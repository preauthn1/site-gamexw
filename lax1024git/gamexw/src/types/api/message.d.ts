export type MessageSystemItem = {
  action_id: number;
  add_time: string;
  content: string;
  id: number;
  img: string;
  link: string;
  link_title: string;
  read: number;
  title: string;
};

export type MessageListItem = {
  add_time: string;
  content: string;
  data: {
    currency: string;
    price: string;
  };
  id: number;
  read: number;
  recharge_first: number;
  title: string;
  type: number;
};
