import mitt, { EventType } from "mitt";
export default mitt<Record<EventType, unknown>>();

// on	注册一个监听事件，用于接收数据
// emit	调用方法发起数据传递
// off	用来移除监听事件

// on 的参数：
// 参数	类型	作用
// type	string | symbol	方法名
// handler	function	接收到数据之后要做什么处理的回调函数
// 这里的 handler 建议使用具名函数，因为匿名函数无法销毁。

// emit 的参数：
// 参数	类型	作用
// type	string | symbol	与 on 对应的方法名
// data	any	与 on 对应的，允许接收的数据


// off 的参数：
// 参数	类型	作用
// type	string | symbol	与 on 对应的方法名
// handler	function	要删除的，与 on 对应的 handler 函数名
