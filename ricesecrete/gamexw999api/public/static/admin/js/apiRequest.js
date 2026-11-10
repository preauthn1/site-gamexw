function adminRequest($url,json){//区域请求
	return new Promise(function(resolve,reject){
		axios.post($url,json).then(function (response) {	
			if (response.data.code==991) {
				window.top.admin_not_login();//未登录
			}
    		resolve(response)//Promise返回成功
    	}).catch(error=>{
    		reject('请求失败');
    	});
	});
}