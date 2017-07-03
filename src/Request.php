<?php
/**
 * DuerOS对Bot的请求封装
 * @author yuanpeng01@baidu.com
 **/
namespace Baidu\Duer\Botsdk;

class Request {
    /**
     * 当前请求的类型，对应request.type
     **/
    private $requsetType;

    /**
     * Session
     **/
    private $session;

    /**
     * UIC 用户信息
     **/
    private $arrUserProfile;

    /**
     * NLU
     **/
    private $nlu;

    /**
     * 原始数据
     **/
    private $data;

    /**
     * ??
     **/
    private $backendServiceInfo;


    /**
     * 设备信息。比如闹钟列表
     **/
    private $deviceData;

    /**
     * @desc 返回request data
     * @param null
     * @return array
     **/
    public function getData(){
        return $this->data; 
    }

    /**
     * @desc 返回用户信息
     * @param null
     * @return array
     **/
    public function getUserProfile() {
        return $this->arrUserProfile;
    }

    /**
     * @desc 返回session
     * @param null
     * @return Session
     **/
    public function getSession() {
        return $this->session;
    }

    /**
     * @desc 返回nlu
     * @param string $domain
     * @return Nlu
     **/
    public function getNlu(){
        return $this->nlu;
    }
    
    
    /**
     * @desc 返回设备信息
     * @param null
     * @return Nlu
     **/
    public function getDeviceData(){
        return $this->deviceData;
    }

    /**
     * @param null
     * @return array
     **/
    public function getUserInfo() {
        return $this->data['user_info'];
    }
    
    public function getType() {
        return $this->requsetType;
    }


    /**
     * @param null
     * @return string
     **/
    public function getUserId() {
        return $this->data['context']['system']['user']['userId']; 
    }

    /**
     * @desc 获cuid
     * @param null
     * @return string
     **/
    public function getCuid() {
        return $this->data['cuid'];
    }


    /**
     * @param null
     * @return string
     **/
    public function getQuery() {
        if($this->requsetType == 'IntentRequest') {
            return $this->data['request']['query']['original'];
        }
        return '';
    }


    /**
     * @param null
     * @return array
     **/
    public function getLocation() {
        return $this->data['location'];
    }
    



    /**
     * @param null
     * @return boolean
     **/
    public function isLaunchRequest(){
        return $this->data['request']['type'] == 'LaunchRequest';
    }

    /**
     * @param null
     * @return boolean
     **/
    public function isSessionEndRequest(){
        return $this->data['request']['type'] == 'SessionEndRequest';
    }

    /**
     * 获取log_id
     * @param null
     * @return string
     */
    public function getLogId() {
        return $this->data['log_id'];
    }
    
    /**
     * @param null
     * @return string
     **/
    public function getBotId() {
        return $this->data['context']['system']['bot']['botId']; 
    }

    /**
     * @param array
     * @return null
     **/
    public function __construct($data) {
        $this->data = $data;
        $this->requsetType = $data['request']['type'];
        $this->session = new Session($data['session']);
        if($this->requsetType == 'IntentRequest') {
            $this->nlu = new Nlu($data['request']['intents']);
        }
    }
}

