# 返回顶部按钮组件使用说明

## 文件位置
- JavaScript文件：`/assets/js/back-to-top.js`

## 快速使用

### 1. 在HTML页面中引入JS文件
```html
<script src="../assets/js/back-to-top.js"></script>
```

### 2. 初始化组件
```javascript
$(document).ready(function() {
    // 使用默认配置
    BackToTop.init();
    
    // 或者使用自定义配置
    BackToTop.init({
        threshold: 200,        // 滚动200px后显示按钮
        duration: 400,         // 滚动动画持续时间(ms)
        position: 'right',     // 按钮位置: 'right' 或 'left'
        bottom: 30,           // 距离底部距离(px)
        size: 50,             // 按钮大小(px)
        icon: '↑',           // 按钮图标
        backgroundColor: '#007bff',
        hoverColor: '#0056b3'
    });
});
```

## 配置选项

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| threshold | number | 300 | 滚动多少像素后显示按钮 |
| duration | number | 500 | 滚动动画持续时间(毫秒) |
| position | string | 'right' | 按钮位置：'right' 或 'left' |
| bottom | number | 30 | 距离底部距离(像素) |
| size | number | 50 | 按钮大小(像素) |
| icon | string | '↑' | 按钮显示的图标 |
| backgroundColor | string | '#007bff' | 按钮背景色 |
| hoverColor | string | '#0056b3' | 按钮悬停色 |

## API方法

### BackToTop.init(options)
初始化返回顶部按钮

### BackToTop.destroy()
销毁返回顶部按钮

### BackToTop.getInstance()
获取当前实例

## 已实现的页面

当前已在以下页面添加了返回顶部按钮：
- `/viewer/inventory.php` - 库存查询页面
- `/viewer/processing_inventory.php` - 加工库存页面

## 特性

✅ **响应式设计** - 自适应移动端和桌面端
✅ **平滑动画** - 使用缓动函数实现平滑滚动
✅ **键盘支持** - 支持空格键和回车键
✅ **无障碍访问** - 包含ARIA标签
✅ **主题适配** - 支持暗色主题
✅ **性能优化** - 使用requestAnimationFrame和passive事件监听
✅ **可定制性** - 丰富的配置选项

## 添加到新页面

要在新页面添加返回顶部按钮，只需：

1. 引入JS文件：
```html
<script src="../assets/js/back-to-top.js"></script>
```

2. 初始化：
```html
<script>
$(document).ready(function() {
    BackToTop.init();
});
</script>
```

## 样式定制

组件使用CSS变量，可以通过修改`back-to-top.js`中的`backToTopCSS`来自定义样式。