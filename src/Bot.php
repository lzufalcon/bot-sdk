<?php
/**
 * Bot-sdk基类。使用都需要继承这个类
 * @author yuanpeng01@baidu.com
 **/
namespace Baidu\Duer\Botsdk;

abstract class Bot{

    private $handler = [];
    private $intercept = [];
    private $event = [];

    /**
     * DuerOS对Bot的请求
     **/
    public $request;

    /**
     * Bot返回给DuerOS的结果
     **/
    public $response;

    /**
     * DuerOS提供的session。
     * 短时记忆能力
     **/
    public $session;

    /**
     * 度秘NLU对query解析的结果
     **/
    public $nlu;
    
    /**
     * @param array $postData us对bot的数据。默认可以为空，sdk自行获取
     * @return null
     **/
    public function __construct($postData=[] ) {
        if(!$postData){
            $rawInput = file_get_contents("php://input");
            $rawInput = str_replace("", "", $rawInput);
            $postData = json_decode($rawInput, true);
            //Logger::debug($this->getSourceType() . " raw input" . $raw_input);
        }
        $this->request = new Request($postData);

        $this->session = $this->request->getSession();

        $this->nlu = $this->request->getNlu();
        $this->response = new Response($this->request, $this->session, $this->nlu);
    }


    /**
     * @desc 条件处理。顺序相关，优先匹配先添加的条件：
     *       1、如果满足，则执行，有返回值则停止
     *       2、满足条件，执行回调返回null，继续寻找下一个满足的条件
     * @param string|array $mix
     * @param function $func
     * @return null
     **/
    protected function addHandler($mix, $func=null){
        if(is_string($mix) && $func) {
            $arr = [];
            $arr[] = [$mix => $func]; 
            $mix = $arr;
        }

        if(!is_array($mix)) {
            return; 
        }
        foreach($mix as $item){
            foreach($item as $k => $v) {
                if(!$k || !$v) {
                    continue; 
                }

                $this->handler[] = [
                    'rule' => $k,
                    'func' => $v,
                ];
            }
        }
    }

    /**
     * @desc  拦截器
     *        1、在event处理、条件处理之前执行Intercept.preprocess，返回非null，终止后续执行。将返回值返回
     *        1、在event处理、条件处理之之后执行Intercept.postprocess
     *
     * @param Intercept $intercept
     * @return null;
     **/
    protected function addIntercept(Intercept $intercept){
        $this->intercept[] = $intercept;
    }

    /**
     * @desc 绑定一个端上事件的处理回调。有event，不执行handler
     * @param string  $event。namespace.name
     * @param function $func
     * @return null
     **/
    protected function addEventListener($event, $func){
        if($event && $func) {
            $this->event[$event] = $func;
        }
    }

    /**
     * @desc 快捷方法。获取当前intent的名字
     *
     * @param null
     * @return string
     **/
    public function getIntentName(){
        if($this->nlu){
            return $this->nlu->getIntentName();
        }
    }

    /**
     * @desc 快捷方法。获取session某个字段，call session的getData
     * @param string $field
     * @param string $default
     * @return string
     **/
    public function getSessionAttribute($field=null, $default=null){
        return $this->session->getData($field, $default);
    }

    /**
     * @desc 快捷方法。设置session某个字段，call session的setData
     *       key: a.b.c 表示设置session['a']['b']['c'] 的值
     * @param string $field
     * @param string $value
     * @param string $default
     **/
    public function setSessionAttribute($field, $value, $default=null){
        return $this->session->setData($field, $value, $default); 
    }

    /**
     * @desc 快捷方法。清空session，call session的clear
     * @param null
     * @return null
     **/
    public function clearSessionAttribute(){
        return $this->session->clear(); 
    }

    /**
     * @desc 快捷方法。获取一个槽位的值，call nlu中的getSlot
     * @param string $field
     * @return string
     **/
    public function getSlot($field){
        if($this->nlu){
            return $this->nlu->getSlot($field);
        }
    }

    /**
     * @desc 快捷方法。设置一个槽位的值，call nlu中的setSlot
     * @param string $field
     * @param string $value
     * @return string
     **/
    public function setSlot($field, $value){
        if($this->nlu){
            return $this->nlu->setSlot($field, $value); 
        }
    }

    /**
     * @desc 告诉DuerOS，在多轮对话中，等待用户的回答
     *       注意：如果有设置Nlu的ask，自动告诉DuerOS，不用调用
     * @param null
     * @return null
     **/
    public function waitAnswer(){
        //should_end_session 
        $this->response->setShouldEndSession(false);
    }

    /**
     * @desc 告诉DuerOS，需要结束对话
     *
     * @param null
     * @return null
     **/
    public function endDialog(){
        $this->response->setShouldEndSession(true);
    }

    /**
     * @desc 事件路由添加后，需要执行此函数，对添加的条件、事件进行判断
     *       将第一个return 非null的结果作为此次的response
     *
     * @param boolean $build  false：不进行封装，直接返回handler的result
     * @return array|string  封装后的结果为json string
     **/
    public function run($build=true){
        //handler event
        $eventHandler = $this->getRegisterEventHandler();

        //check domain
        if($this->request->getType() == 'IntentRequset' && !$this->nlu && !$eventHandler) {
            return $this->response->defaultResult(); 
        }

        //intercept beforeHandler
        $ret = [];
        foreach($this->intercept as $intercept) {
            $ret = $intercept->preprocess($this);
            if($ret) {
                break; 
            }
        }

        if(!$ret) {
            //event process
            if($eventHandler) {
                $event = $this->request->getEventData();
                $ret = $this->callFunc($eventHandler, $event); 
            }else{
                $ret = $this->dispatch();
            }
        }

        //intercept afterHandler
        foreach($this->intercept as $intercept) {
            $ret = $intercept->postprocess($this, $ret);
        }

        if(!$build) {
            return $ret; 
        }
        return $this->response->build($ret);
    }

    /**
     * @param null
     * @return array
     **/
    protected function dispatch(){
        if(!$this->handler) {
            return; 
        }

        foreach($this->handler as $item) {
            if($this->checkHandler($item['rule'])) {
                $ret = $this->callFunc($item['func']);
                
                if($ret) {
                    return $ret;
                }
            }
        }
    }

    /**
     * @param null
     * @return function
     **/
    private function getRegisterEventHandler() {
        $eventData = $this->request->getEventData();
        if($eventData['type']) {
            $key = $eventData['type'];
            if($this->event[$key]) {
                return $this->event[$key];
            }
        }
    }

    /**
     * @param function $func
     * @param mixed  $arg
     * @return mixed
     **/
    private function callFunc($func, $arg=null){
        $ret;
        if(is_string($func)){
            $ret = call_user_func([$this, $func], [$arg]);
        }else{
            $ret = $func($arg); 
        }

        return $ret;
    }

    /**
     * @param string $rule
     * @return array
     * [
     *     [
     *         'type' => 'str',
     *         'value' => 'babab\'\"ab session slot #gagga isset > gag',
     *     ],
     *     [
     *         'type' => 'no_str',
     *         'value' => '#intent',
     *     ],
     * ]
     **/
    private function getToken($rule){
        $token = [];
        return $this->_getToken($token, $rule);
    }

    /**
     * @param null
     * @return null
     **/
    private function _getToken(&$token, $rule) {
        if($rule === "" || $rule === null) {
            return $token; 
        }

        $rgCom = '/[^"\']*/';
        preg_match($rgCom, $rule, $m);
        $token[] = [
            "type" => "no_str",
            "value" => $m[0],
        ];

        $last = substr($rule, mb_strlen($m[0]));
        if($last !== "" || $last !== null){
            for($i=1;$i<mb_strlen($last);$i++){
                $c = $last[$i];
                if($c == "\\"){
                    ++$i;
                    continue;
                }

                if($c == $last[0]){
                    $s = substr($last, 0, $i + 1);
                    $last = substr($last, mb_strlen($s));
                    $token[] = [
                        "type" => "str",
                        "value" => $s,
                    ];

                    break;
                }
            }
        }

        if($last){
            return $this->_getToken($token, $last);
        }

        return $token;
    }

    /**
     * @param string $handler
     * @return boolean
     **/
    private function checkHandler($handler){
        $token = $this->getToken($handler);
        if(!is_array($token)) {
            return false; 
        }

        $arr = []; 
        foreach($token as $t) {
            if($t['type'] == 'str') {
                $arr[] = $t['value']; 
            }else{
                $arr[] = $this->tokenValue($t['value']); 
            }
        }
        
        $str = implode('', $arr);
        //字符串中有$
        $str = str_replace('$', '\$', $str);
        //var_dump($str);
        $func = create_function('', 'return ' . implode('', $arr) . ';');
        return $func();
    }

    /**
     * @param string $str
     * @return string
     **/
    private function tokenValue($str){
        if($str === '' || $str === null) {
            return ''; 
        }

        $rg = [
            'intent' => '/#([\w\.\d_]+)/',
            'session' => '/session\.([\w\.\d_]+)/',
            'slot' => '/slot\.([\w\d_]+)/',
            'requestType' => '/^(LaunchRequest|SessionEndedRequest)$/',
        ];

        $self = $this;
        foreach($rg as $k=>$r) {
            $str = preg_replace_callback($r, function($m) use($self, $k){
                if($k == 'intent'){
                    return json_encode($self->getIntentName() == $m[1]);
                }else if($k == 'session') {
                    return json_encode($self->getSessionAttribute($m[1]));
                }else if($k == 'slot') {
                    return json_encode($self->getSlot($m[1]));
                }else if($k == 'requestType') {
                    return json_encode($self->request->getType() == $m[1]);
                }
            }, $str); 
        }

        return $str;
    }

}
