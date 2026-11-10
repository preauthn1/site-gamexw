// 取出数组中的item项类型
export type ElementType<T> = T extends (infer U)[] ? U : never;

// 取出对象中的值，常用于枚举对象值限制
export type ValueOf<T> = T[keyof T];
