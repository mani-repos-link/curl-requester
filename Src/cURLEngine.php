<?php

/**
 * cUrlRequest is lib to handle...
 * Created by Phoenix404.
 * User: Phoenix404
 * Date: 10/09/2017
 * Start time: 18:30
 * End time: 20:00
 * Version: 0.0.1
 *
 * In next version, will add stream_context_create support if curl is not installed.
 *
 */


namespace cURLRequester;
use Useragent\UserAgent;

class cURLEngine {

    protected $cURL             = null;

    protected $result           = "";
    protected $error            = "";
    protected $userAgent        = "";
    protected $url              = "";

    // Arrays
    protected $options          = array();
    protected $headers          = array();
    protected $functionHeaders  = array();
    protected $invokable 	   = array();
    protected $errors           = array();

    // App/Lib Identity
    public $appName             = "cURLRequester";
    public $appVersion          = "1.0.0";

    // Default Directories where data can be write
    public $CookiesJar          = "./Cookies/Cookies.cookies";
    public $CookiesFile         = "./Cookies/Cookies.cookies";
    public $certPath            = './SSL/cacert.pem';


    public $cacheDir            = './Cache/';

	// Flags
    protected $isCookiesCalled  = false;
    protected $recallUseCache   = false;

	// need to set proxy also
	// need to set cookies
	// get
	// post and fields..
	// google dns
	// need to add time loaded requested
	// curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
    // https://en.wikipedia.org/wiki/List_of_HTTP_header_fields
    // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers
    // authentication
    // download file
    // upload file
    // ftp
    // need to delete @see comments ..
    // content-type which type data send

    /**
	 * cURLRequest constructor
	 * @param string $url
	 * @param bool $ssl
	 * @param array $options
	 * @throws \Exception
	 */
	function __construct($url="", $ssl=false, $options=[])
	{
		if(!function_exists("curl_init"))
			throw new \Exception("cURL is not installed!");

        // Check if directories Exists or not..
        $this->setLibDirectories();

        if(strlen($url)>0) $this->setUrl($url);

        if($ssl) $this->setCertificateFile($this->certPath);

        if(!empty($options)) $this->setOptions($options);

        $this->init_cURL();

        return $this;
	}

    /**
     * Set Library folders
     * @param array $directories
     * @param string $path
     * @param string $permission
     * @param bool $recursive
     */
    protected function setLibDirectories($directories=[], $path="", $permission = "0777", $recursive=true)
    {
        if(empty($directories)) {
            $dirs = ["Cache", "Cookies", "SSL", "Other" => ["Headers"]];
        }else {
            if(is_array($directories)) $dirs   = $directories;
            else $dirs   = array($directories);
        }
        if(strlen($path)<=0)
            $path 	= __DIR__.DIRECTORY_SEPARATOR;

        foreach ($dirs as $key=> $dir) {
            //check if $dir is array
            // then key will be a folder that will hold subfolders are in $dir
            if(is_array($dir)){
                if(!is_dir($path.$key)) mkdir($path.$key, $permission, $recursive);
                $this->setLibDirectories($dir, $path.$key.DIRECTORY_SEPARATOR, $permission, $recursive);
            }else
                if(!is_dir($path.$dir)) mkdir($path.$dir, $permission, $recursive);
        }
    }
    	
    /**
     * @param $options
     */
    public function setOptions($options)
    {
        if(isset($this->options["options"]))
            $this->options["options"] = array_merge($this->options["options"], compact($options));
        else
            $this->options["options"]   = compact($options);
    }

    /**
     * Initialize cURL
     * @param bool $fresh_no_cache
     * @internal param bool $fresh
     * @return $this
     */
    public function init_cURL($fresh_no_cache=false)
    {

        if(!is_resource($this->cURL))
            $this->cURL = curl_init();

        curl_reset($this->cURL);

        $this->freshConnection($fresh_no_cache);
        return $this;
    }

    /**
     * Reset attributes and curl request
     * @return $this
     */
    public function resetCurl()
    {

        $this->result           = "";
        $this->error            = "";
        $this->recallUseCache   = false;
        $this->userAgent        = "";
        $this->options          = array();
        $this->invokable        = array();
        $this->headers          = array();
        $this->url              = array();
        $this->functionHeaders  = array();

        if(is_resource($this->cURL)) curl_reset($this->cURL);

        return $this;
    }

    /**
	 * Close curl connection
	 */
	public function closeCurl()
	{
		curl_close($this->cURL);
	}

    /**
     * Check whether curl opt is set or not
     * @param $opt
     * @return bool
     */
    public function isSetCurlOpt($opt)
	{
		return isset($this->options["curl_opt"][$opt]);
	}

    /**
     * Remove the curl option if is set
     * @param $opt
     * @return $this
     */
    public function removeCurlOpt($opt)
    {
        if($this->isSetCurlOpt($opt))
        {
            unset($this->options["curl_opt"][$opt]);
        }
        return $this;
    }

    /**
	 * Set cURL options
	 * @see http://php.net/manual/en/function.curl-setopt.php
	 * @param $option
	 * @param $value
	 * @return $this
	 */
	public function setOpt($option, $value)
	{
	   $this->options["curl_opt"][$option]=$value;
	   return $this;
	}

    /**
     * Get cURL set option
     * @param string $opt
     * @return null
     */
    protected function getOpt($opt="")
    {
        return $this->isSetCurlOpt($opt)?$this->options["curl_opt"][$opt]:null;
    }

    /**
     * Determine if given option is native opt of curl or not
     * @param null $opt
     * @param array $constArr
     * @return bool|mixed|null|string
     */
    protected function isCurlNativeOpt($opt = null, $constArr=[])
    {
        if(is_null($opt) ||  strlen($opt)<=0 || $opt === 0)
            return false;

        if(is_string($opt))
        {
            $opt = strtoupper($opt);
            if(strpos($opt, "CURL") === false) return false;

            if(defined(strtoupper($opt))) return constant($opt); // return constant value

        }elseif(is_int($opt)){
            if(empty($constArr))
                $constArr = get_defined_constants(1);

            if(!in_array($opt, $constArr["curl"]))
                return false;

            return $opt;
        }

        return false;
    }

    /**
     * Get all options in options array
     * Check for curl options as strings(constants) or numbers
     * @param null $optArr
     * @return null
     */
    protected function resetCurlOptions($optArr = null)
    {
        if(is_null($optArr))
            $optArr     = $this->options["curl_opt"];

        if(!is_array($optArr))
            $optArr     = array($optArr=>$optArr);

        $curlOptions = null;
        $constants   = get_defined_constants(1);

        foreach($optArr as $key => $option)
        {
            $opt = $this->isCurlNativeOpt($key, $constants);
            if($opt == false) continue;
            $curlOptions[$opt] = $option;
        }
        return $curlOptions;
    }

    /**
     * Init Curl setup option
     */
    protected function prepareCurlOption()
    {
        $options    = $this->resetCurlOptions();
        if(is_array($options)){
            curl_setopt_array($this->cURL, $options);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function invoke()
    {
        $this->functionHeaders = array();
        //To check if user has called useCache method before setting the url
        if($this->recallUseCache) $this->enableCache(true);

        // If invokable array is empty
        // It executes the request
        if(!$this->hasFalseValue($this->invokable))
        {
            echo "\nPlease remove me from line ".__LINE__." I am from invoke method.\n";

            //avoid print the result and force it to return the result in a variable
            curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->cURL, CURLOPT_HEADERFUNCTION, array($this, "cURLHeadersFunction"));
            $this->prepareCurlOption();

            print_r($this->options["curl_opt"]);
            $this->result = curl_exec($this->cURL);
        }

        // if cache is enabled
        if(isset($this->options["cacheFileName"]))
        {
            file_put_contents($this->options["cacheFileName"], $this->result);
            unset($this->options["cacheFileName"]);
        }

        // Reinitialize the array
        // So in next request we get new invokable status
        $this->invokable 	= array();

        //Check if result is true
        if($this->result == false)
            $this->result = $this->getError();

        return $this->result;
    }

    /**
     * Curl Header function to get response header info
     * @param $curl
     * @param $header
     * @return int
     */
    protected function cURLHeadersFunction($curl, $header)
    {
        $len = strlen($header);
        if($this->str_contains(strtolower($header), "http"))
        {
            // status  HTTP/1.1 200 OK;
            $this->functionHeaders["status"]    = $header;
        }

        $header = explode(':', $header, 2);
        if (count($header) < 2)
            return $len;

        $name = strtolower(trim($header[0]));
        if (!array_key_exists($name, $this->functionHeaders))
            $this->functionHeaders[$name] = [trim($header[1])];
        else
            $this->functionHeaders[$name][] = trim($header[1]);

        return $len;

    }

    /**
     * Check whether has false value
     * @param $arr
     * @return bool
     */
    protected function hasFalseValue($arr)
    {
        foreach ($arr as $key => $value)
        {
            if($value == false) return true;
        }
        return false;
    }

    /**
     * https://curl.haxx.se/libcurl/c/libcurl-errors.html
     * http://php.net/manual/en/function.curl-strerror.php
     */
    public function getError()
    {
        // get the current executed request error number
        $errno  = curl_errno($this->cURL);
        if($errno>0) {
            $errorMessage = curl_strerror($errno);
            return $this->error = "cURL error (" . $errno . "): " . $errorMessage;
        }

        // return false if request proceed successfully
        return $this->result;
    }

    /**
     * Get request info
     * @param string $opt
     * @return mixed
     */
    public function getCurlInfo($opt="")
    {
        if(!is_resource($this->cURL)) $this->init_cURL();

        $option = $this->isCurlNativeOpt($opt);

        if($option) return curl_getinfo($this->cURL, $option);

        $info   = curl_getinfo($this->cURL);
        if(isset($info[$opt])) return $info[$opt];

        if(strlen($opt)<=0) return $info;
        return false;
    }

    /**
     * @param bool $val
     * @return $this
     */
    public function noBody($val=true)
    {
        $this->setOpt("CURLOPT_NOBODY", $val);
        return $this;
    }

    /**
     * @param $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url  = $url;
        $this->setOpt("CURLOPT_URL", $url);
        return $this;
    }

    /**
     * Determine If file exists on web
     * @param $path
     * @return bool
     */
    protected function uriFileExists($path)
    {
        // need to improve with ftp
        // in next version
        if (filter_var($path, FILTER_VALIDATE_URL) === FALSE)
            return false;

        $headers=get_headers($path);
        return stripos($headers[0],"200 OK")?true:false;
    }

    /**
     * Set .pem files
     * @param string $path
     * @return $this
     * @throws \Exception
     */
    public function setCertificateFile($path="")
	{
		if(strlen($path)<=0)
			$path = $this->certPath;

		if(@file_exists($path))
		{
		    $this->verifyPeer(true);
            $this->setOpt("CURLOPT_CAINFO", realpath($path));
            return $this;
        }

        if($this->uriFileExists($path))
        {
            $this->setOpt("CURLOPT_CAINFO", $path);
            $this->verifyPeer(true);
            return $this;
        }

        throw new \Exception("Cacert path doesn't exists!");

	}

    /**
     * Set directory of certificates
     * @param string $path
     * @return $this
     * @throws \Exception
     */
    public function setCertificatePath($path="")
    {
        if(strlen($path)<=0) {
            $path = $this->certPath;
        }

        if(@is_dir($path))
        {
            $this->verifyPeer(true);
            $this->setOpt("CURLOPT_CAPATH", realpath($path));
            return $this;
        }

        if($this->uriFileExists($path))
        {
            $this->verifyPeer(true);
            $this->setOpt("CURLOPT_CAPATH", $path);
            return $this;
        }

        throw new \Exception("Certificate directory doesn't exists!");
    }

    /**
     * Set Cookies file in which you want to write the cookies
     * @param string $jar
     * @return $this
     */
    public function setCookiesJar($jar="")
    {
        if(strlen($jar)<=0) {
            $jar = $this->CookiesJar;
        }

        if(@is_dir($jar))
        {
            $this->setOpt("CURLOPT_COOKIEJAR", realpath($jar).".cookies");
            return $this;
        }

        if($this->uriFileExists($jar))
        {
            $this->setOpt("CURLOPT_COOKIEJAR", $jar);
            return $this;
        }

        if(!file_exists($jar))
        {
            file_put_contents($jar, "");
        }

        $this->setOpt("CURLOPT_COOKIEJAR", realpath($jar));
        return $this;
    }

    /**
     * Set cookies file in which the cookies are already written.
     * @param string $file
     * @return $this
     */
    public function setCookiesFile($file="")
    {
        if(strlen($file)<=0) {
            $file = $this->CookiesFile;
        }

        /**
         * This options does not work correctly yet.
         */
        if($this->uriFileExists($file))
        {
            $this->setOpt("CURLOPT_COOKIEFILE", realpath($file));
            return $this;
        }

        if(!file_exists($file))
        {
            file_put_contents($file, "");
        }
        $this->setOpt("CURLOPT_COOKIEFILE", realpath($file));
        return $this;
    }

    /**
     * Set Cookies with custom key and values. Where key can be name or array of cookies
     * @see https://stackoverflow.com/questions/6453347/php-curl-and-setcookie-problem
     * @param array|string $key
     * @param string $value
     * @return $this
     */
    public function setCookies($key="", $value="")
    {
        $cookies = "";
        if(is_array($key))
        {
            $cookies    = http_build_query($key);
        }elseif(is_string($key))
        {
            $cookies    = http_build_query(array($key=>$value));
        }

        if(empty($cookies))
            return $this;

        $this->setOpt("CURLOPT_COOKIE", $cookies);
        return $this;
    }

    /**
     * It will enable the Cookies and ll write in a file
     * You can change default value just by assigning value to $this->CookiesJar or $this->CookiesFile
     * @param bool $val
     * @return $this
     */
    public function enableCookies($val = true)
    {
        echo "\nI have been called from cookies(enableCookies method).";
        if($val){
            $this->setCookiesJar();
            $this->setCookiesFile();
        }else{
            $this->removeCurlOpt("CURLOPT_COOKIEJAR");
            $this->removeCurlOpt("CURLOPT_COOKIEFILE");
        }
        return $this;
    }

    /**
     * @see https://en.wikipedia.org/wiki/HTTP_referer
     * @param bool $val
     * @return $this
     */
	public function setAutoReferer($val = true)
	{
		$this->setOpt("CURLOPT_AUTOREFERER", $val);
		return $this;
	}

    /**
     * @see https://en.wikipedia.org/wiki/HTTP_referer
     * @param bool $val
     * @return $this
     */
	public function setReferer($val = true)
	{
		$this->setOpt("CURLOPT_REFERER", $val);
		return $this;
	}

    /**
     * set headers
     * @param array|string $key
     * @param string $value
     * @param bool $headerVal
     */
    public function setHeaders($key, $value="", $headerVal=true)
    {
        if(is_array($key)){
            $this->headers     = array_merge($this->headers, $key);
        }else {
            $this->headers[$key] = $value;
        }

        $headers = array();
        foreach ($this->headers as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }

        $this->setOpt(CURLOPT_HEADER, $headerVal);
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Set maximum redirect allow
     * @param int $level
     * @return $this
     */
    public function setMaxRedirects($level= -1)
    {
        $this->setOpt("CURLOPT_MAXREDIRS", $level);
        return $this;
    }

    /**
     * It will follow end location of referer urls
     * @param bool $val
     * @return $this
     */
    public function followLocation($val=true)
    {
        $this->setOpt("CURLOPT_FOLLOWLOCATION ", $val);
        return $this;
    }

    /**
     * Real url is where from the request is executed
     * @return mixed
     */
    public function getRealUrl()
    {
        return $this->getCurlInfo("CURLINFO_EFFECTIVE_URL");
    }

    /**
     * Get Redirected url
     * @return mixed
     */
    public function getRedirectedUrl()
    {
        //return $this->getCurlInfo("CURLINFO_REDIRECT_URL ");
        return $this->getCurlInfo("redirect_url");
    }

    /**
     * Set timeout
     * @param $seconds
     */
    public function setTimeout($seconds=0)
    {
        $this->setOpt(CURLOPT_TIMEOUT, $seconds);
    }

    /**
     * Make lib own useragent
     * @return $this
     */
    protected function makeCustomUserAgent()
    {
        $this->userAgent = "Mozilla/5.0 (cURL; PhoenixOS; 512x) ".$this->appName."Kit/777.77 (KHTML, like Phoenix) ".
            $this->appName."/".$this->appVersion." Phoenix404/777.77";
        return $this;
    }

    /**
     * @param string $str
     * @return $this
     */
    public function setUserAgent($str="random")
	{
        if($str===false)
        {
            $this->removeCurlOpt("CURLOPT_USERAGENT");
            return $this;
        }

        $useragentsReserved = ["windows", "linux",  "mac", "unix", "mozilla", "firefox", "chrome", "ie", "safari",
                                "opera", "maxthon", "android", "kindle", "apple", "blackberry", "acer", "amazonKindle",
                                "GoogleNexus", "HP", "HTC", "LG", "Motorola", "Nokia", "Samsung", "Sony", "Tablets", "mac",
                                "Playstation", "Wii", "PSP", "SuperBot", "Wget", "ELinks", "NetBSD", "Lynx", "IEMobile",
                                "Baiduspider", "iPhone", "Puffin", "Yahoo","Galeon","Symbian","Googlebot-Mobile"];

        $randomUserAgent = in_array(strtolower($str), array_map("strtolower", $useragentsReserved));
	    if($randomUserAgent || strtolower($str)=="random" || $str==true || strlen($str)<=0)
        {
            if(class_exists("\\Useragent\UserAgent")){
                $useragent = new UserAgent();
                if($randomUserAgent)
                {
                    $this->userAgent = $useragent->getRandomUserAgent(strtolower($str));
                }elseif(strtolower($str)=="random")
                {
                    $this->userAgent = $useragent->getRandomUserAgent();
                }else{
                    $this->userAgent = $useragent->getRealUserAgent();
                    if(strlen($this->userAgent)<=0)
                    {
                        $this->makeCustomUserAgent();
                    }
                }
            }else{
                echo "\nclass doesn't exists\n";
                //create custom useragent
                $this->makeCustomUserAgent();
            }
            $this->setOpt("CURLOPT_USERAGENT", $this->userAgent);
        }elseif(strlen($str) > 0){
		    $this->userAgent     = $str;
			$this->setOpt("CURLOPT_USERAGENT", $str);
		}
		return $this;
	}

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Get the size of directory
     * @param $directory
     * @return bool|int
     */
    public function getDirectorySize($directory)
    {
        if(!is_dir($directory))
            return false;

        $size = 0;
        $dirs = scandir($directory);
        foreach($dirs as $dir)
        {
            if($dir === "." || $dir === "..")
                continue;

            if(is_dir($dir)) {

                $size += $this->getDirectorySize($directory.DIRECTORY_SEPARATOR.$dir);
            }

            if(is_file($directory.DIRECTORY_SEPARATOR.$dir))
            {
                $size += filesize($directory.DIRECTORY_SEPARATOR.$dir);
            }
        }

        return $size;
    }

    /**
     * @param $directory
     * @param $timestamp
     * @param string $beforeEqualAfter|<|<=|=|>=|>
     * @return bool
     */
    public function autoDeleteFiles($directory, $timestamp, $beforeEqualAfter="<")
    {
        if(!is_dir($directory)) return false;
        $dirs = scandir($directory);
        foreach($dirs as $dir) {
            if ($dir === "." || $dir === "..")
                continue;

            if (is_dir($dir))
                $this->getDirectorySize($directory . DIRECTORY_SEPARATOR . $dir);

            if (is_file($directory . DIRECTORY_SEPARATOR . $dir))
            {
                switch ($beforeEqualAfter) {
                    case("="):
                        if (filemtime($directory . DIRECTORY_SEPARATOR . $dir) === $timestamp)
                            unlink($directory . DIRECTORY_SEPARATOR . $dir);
                        break;
                    case("<"):
                        if (filemtime($directory . DIRECTORY_SEPARATOR . $dir) < $timestamp)
                            unlink($directory . DIRECTORY_SEPARATOR . $dir);
                        break;
                    case("<="):
                        if (filemtime($directory . DIRECTORY_SEPARATOR . $dir) <= $timestamp)
                            unlink($directory . DIRECTORY_SEPARATOR . $dir);
                        break;
                    case(">"):
                        if (filemtime($directory . DIRECTORY_SEPARATOR . $dir) > $timestamp)
                            unlink($directory . DIRECTORY_SEPARATOR . $dir);
                        break;
                    case(">="):
                        if (filemtime($directory . DIRECTORY_SEPARATOR . $dir) >= $timestamp)
                            unlink($directory . DIRECTORY_SEPARATOR . $dir);
                        break;
                }
            }

        }
        return true;
    }

    /**
     * $option["MaxDiskSize"] = 10*1024*1024. //max 10mb of Cache directory
     * $option["MaxFileOldDuration"] = (60*60*24)*7. // if file exists and not older than 7 days
     * $option["MinFileSize"]  = 2. // Minimum file size in byte to check if it has some data from previous request if not it will new req
     * then give back result of this file instead of sending request to server
     * @param array $option
     * @return array
     */
    public function setCacheSetting($option=[])
    {
        // Check if option array is empty and $this->options["cacheOptions"] exists
        // We return $this->options["cacheOptions"] to avoid to change previous user/default values

        if((count($option) == 0 || empty($option)))
        {
            if(isset($this->options["cacheOptions"])) {
                if (is_array($this->options["cacheOptions"]))
                    return $this->options["cacheOptions"];
            }
        }else {
                // add or modify new options add by user
                $this->options["cacheOptions"]   = $option;
                // get new fresh key/values
                $option     = $this->options["cacheOptions"];
        }


        // Lets say, our src/cache folder can have only 10mb by default
        if(!isset($option["MaxDiskSize"]))
            $option["MaxDiskSize"]  = 10*1024*1024;

        // Lets say, a file can have old duration
        // if file is older than (60*60*24)*7.. so we will make new request
        if(!isset($option["MaxFileOldDuration"]))
            $option["MaxFileOldDuration"]  = (60*60*24)*7;

        // Minimum file size in byte to check if it got some data from result or not
        if(!isset($option["MinFileSize"]))
            $option["MinFileSize"]  = 2;

        if(isset($this->options["cacheOptions"])) {
            if (is_array($this->options["cacheOptions"]))
                $this->options["cacheOptions"] = array_merge($this->options["cacheOptions"], $option);
            else
                $this->options["cacheOptions"]  = $option;
        }else
            $this->options["cacheOptions"]  = $option;

        return $this->options["cacheOptions"];
    }

    /**
     * Get cache Setting
     * @param string $val
     * @return mixed
     */
    protected function getCacheSetting($val="")
    {
        // Call the setcacheSetting
        $this->setCacheSetting(is_array($val)?$val:[]);
        if(strlen($val)>0)
        {
            if(isset($this->options["cacheOptions"][$val]))
            {
                return $this->options["cacheOptions"][$val];
            }
        }
        return $this->options["cacheOptions"];
    }

    /**
     * If this method is enable,
     *  - It will check for the file of last request executed of given url
     *  - If it doesn't found it will create the file for given url.
     *  - If file already exists, so it will not make a cURL request and give back response of last time request executed
     * Second parameter by default has following values :
     *  $option["MaxDiskSize"] = 10*1024*1024. //max 10mb of Cache directory
     *  $option["MaxFileOldDuration"] = (60*60*24)*7. // if file exists and not older than 7 days
     *  $option["MinFileSize"]  = 2. // Minimum file size in byte to check if it has some data from previous request if not it will new req
     *  then give back result of this file instead of sending request to server
     *
     *  $option["MinFileSize"] = 2. //cache file that can have minimum file size
     * @param bool $cache |default false
     * @param array $option
     * @return $this|bool
     */
    public function enableCache($cache=false, $option=[])
	{
        //So we send fresh request to server(!)
        if($cache===false){
            $this->freshConnection(true);
            return $this;
        }

        //By default the cache is false
        $this->invokable["cache"] 	= false;
        $this->recallUseCache       = false;
        $option                = $this->getCacheSetting();

        //check weather cache dir exists or not
		if($this->getDirectorySize($this->cacheDir) > $option["MaxDiskSize"])
        {
            //auto maintenance
            // >= delete all file greater than..
            $this->autoDeleteFiles($this->cacheDir, $option["MaxFileOldDuration"], ">=");
            $this->invokable["true"]    = true;
        }

		// check if url is set or not
		// if not, we set the flag useCache to true
		// and we will try to call again this method in invoke method
		if(!$this->isSetCurlOpt("CURLOPT_URL")) {
            $this->recallUseCache = true;
            return $this;
        }

        $fileName   = $this->getCacheFileName();

		if(file_exists($fileName))
		{
		    $fileTime   = filemtime($fileName);
			$timeDiff 		= time() - $fileTime;

			if(filesize($fileName) <= (int)$option["MinFileSize"])
            {
                $this->invokable["cache"] 	= true;
            }

		    if((int)$option["MaxFileOldDuration"] >= $timeDiff)
            {
				$this->result 				= file_get_contents($fileName);
            }else{
                $this->invokable["cache"] 	= true;
            }
        }else {
		    // If cache doesn't exists
		    // then create new file when request is sent to server
            $this->options["cacheFileName"] = $fileName;
            // Force to send request to Server
            $this->invokable["cache"] 	= true;
        }
        $this->recallUseCache 	= false;
		return $this;
	}

    /**
     * @return $this
     */
    public function disableCache()
    {
        $this->enableCache(false);
        return $this;
    }

    /**
     * get the cache file name for current url requested
     * @return bool|string
     */
    public function getCacheFileName()
    {
        if(!$this->isSetCurlOpt("CURLOPT_URL"))
            return false;

        $url    = $this->getOpt("CURLOPT_URL");
        $urlParts = parse_url($url);

        // php 5.*
        $urlParts["host"]    = str_replace("http", "", $urlParts["host"]);
        $urlParts["host"]    = str_replace("https", "", $urlParts["host"]);
        $urlParts["host"]    = str_replace("www", "", $urlParts["host"]);
        $urlParts["host"]    = str_replace(".", "_", $urlParts["host"]);
        //php 7
        //$urlParts["host"]    = str_replace(["www", "https", "http", "."], "", $urlParts["host"]);
        //$urlParts["host"]    = str_replace(["."], "_", $urlParts["host"]);

        $fileName   = $this->cacheDir."/".substr(md5($url),0,10)."_".$urlParts["host"].".cache";
        return $fileName;
    }

    /**
     * Set the cache file name. Would be nice if you set absolute file Path.
     * (You can also set the file on the server where you want to write the cache data) => will be support in next version.
     * It returns true on if file set correctly otherwise false.
     * @param $name
     * @return bool
     */
    public function setCacheDir($name)
    {
        if(@is_dir($name))
        {
            // Check if file is writeAble
            if(!is_writable($name)) return false;
            $this->cacheDir     = realpath($name);
        }elseif($this->uriFileExists($name))
        {
            // Check if file is write able on the server
            // I hope it can check...
            if(is_writable($name)) return false;
            $this->cacheDir = $name;
        }else return false;
        return false;
    }

    /**
     * @param bool $val
     * @return $this
     */
    public function freshConnection($val = true)
	{
	    if($val) $this->setOpt("CURLOPT_FRESH_CONNECT", $val);
	    else $this->removeCurlOpt("CURLOPT_FRESH_CONNECT");
		return $this;
	}

    /**
     * @return string
     */
    public function getResult ()
    {
        return $this->result;
    }

    /**
     * Set Port num
     *
     * @param  $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->setOpt(CURLOPT_PORT, intval($port));
        return $this;
    }

    /**
     * Set Connect Timeout
     * @param  $seconds
     * @return $this
     */
    public function setConnectTimeout($seconds)
    {
        $this->setOpt(CURLOPT_CONNECTTIMEOUT, $seconds);
        return $this;
    }

    /**
     * @param bool $val
     * @return $this
     */
    public function verifyPeer($val=true)
    {
        $this->setOpt("CURLOPT_SSL_VERIFYPEER", $val);
        return $this;
    }

    /**
     * @param bool $val
     * @return $this
     */
    public function verifyHost($val=false)
    {
        $this->setOpt("CURLOPT_SSL_VERIFYHOST", $val);
        return $this;
    }

    /**
     * @return $this
     */
    public function fastTCP()
    {
        $this->setOpt("CURLOPT_TCP_FASTOPEN", true);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSSLResult()
    {
        return $this->getCurlInfo("CURLINFO_SSL_VERIFYRESULT");
    }
    /**
     * @return mixed
     */
    public function getCookieList()
    {
        return $this->getCurlInfo("CURLINFO_COOKIELIST");
    }

    /**
     * @return mixed
     */
    public function getRequestSize()
    {
        return $this->getCurlInfo("CURLINFO_REQUEST_SIZE");
    }

    /**
     * @return mixed
     */
    public function getHTTPCode()
    {
        return $this->getCurlInfo("CURLINFO_HTTP_CODE");
    }

    /**
     * Return http status if exists otherwise an empty string
     * Some time it returns cookies depend on site
     * for example if you request this url https://www.google.it/?gfe_rd=cr&dcr=0&ei=SEfFWcTsKrLBXoHSnagK&gws_rd=ssl
     * it will return you status as cookies..
     * @See https://www.google.com/support/accounts/answer/151657?hl=en
     * @return mixed|string
     */
    public function getHeaderStatus()
    {
        return isset($this->functionHeaders["status"])?$this->functionHeaders["status"]:"";
    }

    /**
     * @return mixed|array|string
     */
    public function getCacheControl()
    {
        return isset($this->functionHeaders["cache-control"])?$this->functionHeaders["cache-control"]:"";
    }

    /**
     * @return mixed|array|string
     */
    public function getContentType()
    {
        return isset($this->functionHeaders["content-type"])?$this->functionHeaders["content-type"]:"";
    }

    /**
     * Returns the connection status of request e.g close, maybe keep-alive..
     * @return mixed|array|string
     */
    public function getConnectionStatus()
    {
        return isset($this->functionHeaders["connection"])?$this->functionHeaders["connection"]:"";
    }

    /**
     * @return mixed|array|string
     */
    public function getServerType()
    {
        return isset($this->functionHeaders["server"])?$this->functionHeaders["server"]:"";
    }

    /**
     * Returns curl Instance
     * @return resource
     */
    public function getInstance()
    {
        return $this->cURL;
    }

    /**
     * @return mixed|array|string
     */
    public function getCookies()
    {
        return isset($this->functionHeaders["set-cookie"])?$this->functionHeaders["set-cookie"]:"";
    }

    /**
     * Get back the given url
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * check whether str contains
     * @param $haystack
     * @param $needles
     * @return bool
     */
    public function str_contains($haystack, $needles)
    {
        foreach ((array) $needles as $needle){
            if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }


}
