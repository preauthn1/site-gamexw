# 开启提示

vscode 配置的自动格式化代码和 **eslint**
配置格式化工具 **vue-Official** **eslint** 每次保存进行代码和格式化
请去扩展中下载该配置

# 公用 less 函数

## common 公用函数

### insert

使用方法
@number 距离四周的数值 20px
例：

```less
.className {
  .insert(20px);
}
```

### size

width 和 height 组合

```less
.className {
  .size(20px);
}
```

## media 媒体查询封装函数

### .sm 小于 540px 使用的样式

```less
.className {
  .sm({
      color:red;
      font-size:16px;
  });
}
```

### .md 小于 768px 使用的样式

```less
.className {
  .md({
      color:red;
      font-size:16px;
  });
}
```

### .lg 小于 1024px 使用的样式

```less
.className {
  .lg({
      color:red;
      font-size:16px;
  });
}
```

### .xl 小于 1440px 使用的样式

```less
.className {
  .xl({
      color:red;
      font-size:16px;
  });
}
```

### .sm-min 大于 540px 使用的样式

```less
.className {
  .sm-min({
      color:red;
      font-size:16px;
  });
}
```

### .md-min 大于 768px 使用的样式

```less
.className {
  .md-min({
      color:red;
      font-size:16px;
  });
}
```

### .lg-min 大于 1024px 使用的样式

```less
.className {
  .lg-min({
      color:red;
      font-size:16px;
  });
}
```

### .xl-min 大于 1440px 使用的样式

```less
.className {
  .xl-min({
      color:red;
      font-size:16px;
  });
}
```

# 公用 js

# 公用 vue 组件
