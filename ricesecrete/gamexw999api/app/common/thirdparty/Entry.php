<?php
namespace app\common\thirdparty;
class Entry{
	
	private $config;

	/**
	 * @param $config
	**/ 
	public function __construct($config){
		$this->config=$config;
	}

    public function action($packageName,$input,$action){
        $obj= "\app\common\\thirdparty\\".$packageName;
        $rdata=(new $obj($this->config))->$action($input);
        return $rdata;
    }
}