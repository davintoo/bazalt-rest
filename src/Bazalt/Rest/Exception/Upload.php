<?php

namespace Bazalt\Rest\Exception;

class Upload extends \Exception
{
    const UPLOAD_ERR_POST_HOOK = 9; 
    
    private $_allowedExtensions = array();

    /**
     * __construct
     *
     * @param int $code
     */
    public function __construct($code, $allowed = array())
    {
        $message = $this->codeToMessage($code);

        $this->_allowedExtensions = $allowed;

        parent::__construct($message, $code);
    }


    public function getAllowedExtensions()
    {
        return $this->_allowedExtensions;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function toArray()
    {
        $arr = array();
        $arr['error'] = $this->getMessage();
        $arr['code'] = $this->getCode();
        $arr['allowed_extensions'] = $this->_allowedExtensions;
        return $arr;
    }

    /**
     *
     *
     *
     * @param int $code
     *
     * @return string
     */
    private function codeToMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = sprintf(
                    'The uploaded file exceeds the upload_max_filesize directive in php.ini (post_max_size: %s, upload_max_filesize: %s)',
                    ini_get('post_max_size'), ini_get('upload_max_filesize')
                );
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'The uploaded file was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = 'Missing a temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = 'File upload stopped by extension';
                break;
            case self::UPLOAD_ERR_POST_HOOK:
                $message = 'File upload stopped by post upload hook';
                break;
                
            default:
                $message = 'Could not save uploaded file. The upload was cancelled, or server error encountered';
                break;
        }
        return $message;
    }
}
