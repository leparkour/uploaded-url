<?php

namespace Denisyuk;

use Symfony\Component\HttpFoundation\File\File;

class UploadedUrl extends File
{
    public $url,
           $verify     = true,
           $cert       = 'cacert.pem',
           $maxsize,   // null
           $timeout,   // null
           $useragent, // null
           $buffersize = 1024,
           $redirects  = true,
           $maxredirs  = 10,
           $curl       = [];

    private $tmpfile;

    private $curlHandler;
    private $fileHandler;
    public  $errorMessage;
    public  $errorCode;

    public function __construct(
        string $url,
        array  $options = []
    ) {
        $this->url = $url; // todo: isHttpUrl()

        foreach ($options as $name => $value) {
            $this->{$name} = $value;
        }

        $this->init();
    }

    public function __set($name, $value)
    {
        throw new \Error(
            sprintf('The option "%s" does not exist in the configuration of class %s.', $name, __CLASS__)
        );
    }

    private function init()
    {
        $this->tmpfile = new tmpfile;

        $this->request();

        parent::__construct($this->tmpfile);
    }

    private function request()
    {
        $this->curlHandler = curl_init($this->url);
        $this->fileHandler = fopen($this->tmpfile, 'w');

        $this->prepareOptions([
            CURLOPT_FAILONERROR => true,
            CURLOPT_PROTOCOLS   => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FILE        => $this->fileHandler,
        ]);

        curl_exec($this->curlHandler);

        $this->errorMessage = curl_error($this->curlHandler);
        $this->errorCode    = curl_errno($this->curlHandler);

        // todo: curl_getinfo()

        fclose($this->fileHandler);
        curl_close($this->curlHandler);
    }

    private function prepareOptions(array $default)
    {
        if ($this->verify) {
            $default[is_dir($this->cert) ? CURLOPT_CAPATH : CURLOPT_CAINFO] = $this->cert;
        } else {
            $default += [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ];
        }

        if ($this->maxsize) { // todo: '32M' -> 1024 * 1024 * 32
            $default += [
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_BUFFERSIZE       => $this->buffersize,
                CURLOPT_PROGRESSFUNCTION => function ($ch, $dwnldSize, $dwnld, $upldSize, $upld) { // todo: вынести в отдельный метод
                    if ($dwnld > $this->maxsize) {
                        return -1;
                    }
                },
            ];
        }

        $this->timeout and $default[CURLOPT_TIMEOUT] = $this->timeout;

        $default[CURLOPT_USERAGENT] = $this->useragent ?? $_SERVER['HTTP_USER_AGENT'] ?? false;

        if ($this->redirects) {
            $default += [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => $this->maxredirs,
            ];
        }

        return curl_setopt_array(
            $this->curlHandler,
            $default += $this->curl
        );
    }

    public function isValid()
    {
        return $this->errorCode === CURLE_OK;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getErrorMessage()
    {
        if ($this->isValid()) {
             return;
        }

        // todo: перевести на английский?
        $errors = [
            /*  1 */ CURLE_UNSUPPORTED_PROTOCOL => 'Ссылка должна начинаться с http:// или https://.',
            /*  6 */ CURLE_COULDNT_RESOLVE_HOST => 'Укажите корректную ссылку на удалённый файл.',
            /* 22 */ CURLE_HTTP_RETURNED_ERROR  => 'По ссылке "%s" файл не найден.',
            /* 42 */ CURLE_ABORTED_BY_CALLBACK  => 'Файл "%s" не должен превышать %d Мбайт.',
        ];

        $message = isset($errors[$this->errorCode]) ?
                   $errors[$this->errorCode]        :
                   'При загрузке удалённого файла "%s" произошла ошибка.'; // todo: перевести на английский?

        return sprintf(
            $message,
            rawurldecode($this->url),
            $this->maxsize / pow(1024, 2) // todo: 1024 * 1024 * 32 -> 32 MiB
        );
    }
}