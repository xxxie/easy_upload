<?php
namespace Xxxie\EasyUpload;

use Xxxie\EasyUpload\Exceptions\InvalidArgumentsException;
use Xxxie\EasyUpload\Exceptions\HttpException;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

class EasyUpload
{
    protected $access_key;
    protected $secret_key;
    protected $extension;
    protected $bucket_name;

    protected $config;

    public function __construct(array $config)
    {
        $this->access_key = $config['qiniu_access_key'];
        $this->secret_key = $config['qiniu_secret_key'];
        $this->bucket_name = $config['qiniu_bucket'];
        $this->config = $config;
    }

    /**
     * 上传文件到本地
     * @param $file
     * @return string
     * @throws HttpException
     */
    public function uploadLocal($file)
    {
        $this->validator($file);
        $folderPath = $this->getFolderPath();
        $fileName = $this->getFileName($file);
        //拼接文件 的绝对路径/www/wwwroot/www.baidu.com/public/$foldName
        $folderPublicPath = $this->config['public_path'] . '/' . $folderPath;
        if (!is_dir($folderPublicPath)) mkdir($folderPublicPath, 0777, true);
        $filePath = $folderPublicPath . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw  new HttpException('上传文件失败');
        }
        $url = $this->getUrlPath($folderPath, $fileName);
        $info = [
            'status' => 1,
            'message' => 'success',
            'url' => $url;];
        return $info;
    }

    /**
     * 上传文件到七牛
     * @param $file
     * @return array
     */
    public function uploadQiniu($file)
    {
        $this->validator($file);
        try {
            $auth = new Auth($this->access_key, $this->secret_key);
            $token = $auth->uploadToken($this->bucket_name);
            $upManger = new UploadManager();
            $fileName = $this->getFileName($file);
            list($code, $error) = $upManger->putFile($token, $fileName, $file['tmp_name']);
        } catch (\Exception $e) {
            throw  new HttpException($e->getMessage(), $e->getCode(), $e);
        }
        if ($error) {
            return ['status' => 0, 'message' => '上传失败'];
        }
        $url = $info['url'] = $this->config['qiniu_domain'] . '/' . $fileName;
        return ['status' => 1, 'message' => 'success', 'url' => $url, 'hash' => $code['hash']];
    }

    /**
     * 获取保存文件的路径
     * @return string
     */
    protected function getFolderPath()
    {
        //设置文件的保存路径  /uploads/image/$folder/2020/03/17/
        $foldName = $this->config['root_folder'] . $this->config['folder'] . '/' . date($this->config['date_format'], time());

        return $foldName;
    }

    /**
     * 获取文件名
     * @param $file
     * @return string
     */
    protected function getFileName($file)
    {
        $randName = time() . random_int(pow(10, $this->config['random_length'] - 1), pow(10, $this->config['random_length']) - 1);
        if ($this->config['file_prefix']) {
            $fileName = $this->config['file_prefix'] . '/' . $randName . '.' . $this->extension;
            return $fileName;
        }
        return $randName . '.' . $this->extension;
    }

    /**
     * 获取文件路径
     * @param $foldPath
     * @param $fileName
     * @return string
     */
    protected function getUrlPath($foldPath, $fileName)
    {
        if ($this->config['is_url']) {
            $url = $this->config['url'] . $foldPath . '/' . $fileName;
            return $url;
        }
        return $foldPath . '/' . $fileName;
    }

    protected function validator($file)
    {
        if (empty($file['tmp_name'])) {
            throw new InvalidArgumentsException('请选择文件上传!');
        }
        $this->extension = pathinfo($file['name'])['extension'];
        if (!in_array($this->extension, $this->config['allowed_ext'])) {
            throw new InvalidArgumentsException('不允许上传的文件类型:' . pathinfo($file['name']['extension']));
        }
        if (filesize($file['name'] > $this->config['max_size'])) {
            throw  new InvalidArgumentsException('不允许上传超过' . $this->config['max_size'] . '字节的文件');
        }
    }
}