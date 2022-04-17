<?php

class Response
{
    private $_success;
    private $_httpStatusCode;
    private $_messages = array();
    private $_data;
    private $_toCache = false;
    private $_responseData = array();

    public function __construct($success, $httpStatusCode, $message, $data, $toCache)
    {
        $this->setSuccess($success);
        $this->setHttpStatusCode($httpStatusCode);
        $this->addMessage($message);
        $this->setData($data);
        $this->toCache($toCache);
    }

    public function setSuccess($success)
    {
        # code...
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode)
    {
        # code...
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message)
    {
        # code...
        $this->_messages[] = $message;
    }

    public function setData($data)
    {
        # code...
        $this->_data = $data;
    }

    public function toCache($toCache)
    {
        # code...
        $this->_toCache = $toCache;
    }

    public function send()
    {
        # code...
        header('Content-type: application/json; charset=utf-8');

        if ($this->_toCache == true) {
            # code...
            header('Cache-control: max-age=60');
        } else {
            # code...
            header('Cache-control: no-cache, no-store');
        }

        if (($this->_success !== false && $this->_success !== true) || !is_numeric($this->_httpStatusCode)) {
            http_response_code(500);

            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;
            $this->addMessage('Response creation error');
            $this->_responseData['messages'] = $this->_messages;
        } else {
            http_response_code($this->_httpStatusCode);
            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] = $this->_success;
            $this->_responseData['messages'] = $this->_messages;
            if ($this->_data !== null) {
                # code...
                $this->_responseData['data'] = $this->_data;
            }
        }

        echo json_encode($this->_responseData);
    }
}
