<?php

namespace Bazalt\Rest;

define('DEFAULT_MAX_SIZE', 10485760);

class Uploader
{
    protected $allowedExtensions = array();

    protected $sizeLimit = DEFAULT_MAX_SIZE;
    
    private static $postUploadHooks = array();

    public function __construct(array $allowedExtensions = array(), $sizeLimit = DEFAULT_MAX_SIZE)
    {
        $allowedExtensions = array_map("strtolower", $allowedExtensions);

        $this->allowedExtensions = $allowedExtensions;
        $this->sizeLimit = $sizeLimit;
    }
    
    public static function addPostUploadHook($cb)
    {
        self::$postUploadHooks []= $cb;
    }
    
    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    public function handleUpload($uploadDirectory, $pathParams = array())
    {
        if (!is_writable($uploadDirectory)) {
            throw new \Exception(sprintf("Upload directory '%s' isn't writable.", $uploadDirectory));
        }

        if (!isset($_FILES['file'])) {
            throw new Exception\Upload(0); //default message
        }

        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception\Upload($_FILES['file']['error']);
        }

        $size = $this->getFileSize();
        if ($size == 0) {
            throw new Exception\Upload(UPLOAD_ERR_NO_FILE);
        }
        if ($size > $this->sizeLimit) {
            throw new Exception\Upload(UPLOAD_ERR_INI_SIZE);
        }

        $ext = $this->getExt();
        if (($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)) || !$ext) {
            throw new Exception\Upload(UPLOAD_ERR_EXTENSION, $this->allowedExtensions);
        }

        $fileName = md5(uniqid()) . '.' . $ext;
        $filePath = $this->getSavePath($fileName, $pathParams);
        $fullName = $uploadDirectory . $filePath . $fileName;
        @mkdir(dirname($uploadDirectory . $filePath), 0777, true);

        $this->moveUploadedFile($_FILES['file']['tmp_name'], $fullName);

        return array(
            'file' => $filePath . $fileName,
            'extension' => $ext,
            'size' => $size,
            'name' => $_FILES['file']['name']
        );
    }

    public function moveUploadedFile($src, $dst)
    {
        $this->checkPostUpload($src);

        if (is_uploaded_file($src)) {
            if (!move_uploaded_file($src, $dst)) {
                throw new \Exception('Cannot move file ' . $src . ',' . $dst);
            }
        } else {
            if (!rename($src, $dst)) {
                throw new \Exception('Cannot move file ' . $src . ',' . $dst);
            }
        }
    }

    protected function checkPostUpload($file)
    {
        foreach(self::$postUploadHooks as $postUploadHook) {
            $cbRes = call_user_func($postUploadHook, $file);
            if($cbRes !== true) {
                @unlink($file);
                $cbExveption = new Exception\Upload(Exception\Upload::UPLOAD_ERR_POST_HOOK);
                $cbExveption->setMessage($cbRes);
                throw $cbExveption;
            }
        }
    }

    protected function getSavePath($filename, $pathParams)
    {
        $name = '/';
        if (count($pathParams) > 0) {
            $name .= implode('/', $pathParams);
        }
        $name = rtrim($name, '/') . '/';
        $name .= $filename[0] . $filename[1] . '/' . $filename[2] . $filename[3];
        return $name;
    }

    protected function getFileSize()
    {
        return $_FILES['file']['size'];
    }

    protected function getExt()
    {
        $pathinfo = pathinfo($this->getFileName());
        return isset($pathinfo['extension']) ? strtolower($pathinfo['extension']) : null;
    }

    protected function getFileName()
    {
        return $_FILES['file']['name'];
    }
    
    public static function autoRotateImage($src)
    {
        $exif = @exif_read_data($src, 'IFD0');
        $ort = $exif && isset($exif['Orientation']) ? $exif['Orientation'] : 0;

        if($ort > 2) {
            $image = imagecreatefromjpeg($src);
            switch ($ort) {
                case 3: // 180 rotate left
                    $image = imagerotate($image, 180, 0);
                    break;
                case 6: // 90 rotate right
                    $image = imagerotate($image, -90, 0);
                    break;
                case 8:    // 90 rotate left
                    $image = imagerotate($image, 90, 0);
                    break;
            }
            if(!$image) {
                throw new \Exception('Cannot rotate file ' . $src);
            }
            imagejpeg($image, $src, 100);
        }
    }
}
