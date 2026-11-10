;!function (win) {
  "use strict";
  var doc = document
  ,$l = function(){}

  /**
   * [open 打开弹出层]
   * @param  {[type]}  title [弹出层标题]
   * @param  {[type]}  url   [弹出层地址]
   * @param  {[type]}  w     [宽]
   * @param  {[type]}  h     [高]
   * @param  {Boolean} full  [全屏]
   * @return {[type]}        [description]
   */
  $l.prototype.open = function (title,url,w,h,full) {
    if (title == null || title == '') {
          var title=false;
      };
      if (url == null || url == '') {
          var url="404.html";
      };
      if (w == null || w == '') {
          var w=($(window).width()*0.9);
      };
      if (h == null || h == '') {
          var h=($(window).height() - 50);
      };
      if (maxmin == null || maxmin == '') {
         var maxmin = false
      };
      var index = layer.open({
          type: 2,
          area: [w+'px', h +'px'],
          fix: false, //不固定
          maxmin: true,
          shadeClose: true,
          shade:0.4,
          title: title,
          content: url
      });
      if(full){
         layer.full(index); 
      }
  }
  /**
   * [close 关闭弹出层]
   * @return {[type]} [description]
   */
  $l.prototype.close = function() {
    var index = parent.layer.getFrameIndex(window.name);
      parent.layer.close(index);
  };
  /**
   * [close 关闭弹出层父窗口关闭]
   * @return {[type]} [description]
   */
  $l.prototype.father_reload = function() {
      parent.location.reload();
  };

  $l.prototype.ajax = function(url,data){
    return new Promise((resolve, reject) => {
            $.ajax({
                  url : url,
                  dataType : 'html',
                  type : 'post',
                  data:data,
                  success : function(res){
                      if (res.data.code==991) {
                        window.top.admin_not_login();//未登录
                      }
                      resolve(res)//Promise返回成功
                  }
                });    
          return false;
    })
  }

  win.$l = new $l();
}(window);