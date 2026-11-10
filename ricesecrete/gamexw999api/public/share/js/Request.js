function Request($url,json){//区域请求
	return new Promise(function(resolve,reject){
		axios.post($url,json).then(function (response) {	
    		resolve(response)//Promise返回成功
    	}).catch(error=>{
    		reject('请求失败');
    	});
	});
}