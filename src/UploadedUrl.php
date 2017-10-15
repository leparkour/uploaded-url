<?php

namespace Denisyuk\UploadedUrl;

use Symfony\Component\HttpFoundation\File\File;

class UploadedUrl extends File
{
    /**
     * @var string $url        Ссылка на удалённый файл по протоколу http:// или https://.
     * @var bool   $verify     Включить проверку хоста по указанному CA-сертификату.
     * @var string $cert       Имя файла или директории с CA-сертификатами.
     * @var int    $maxsize    Максимальный размер файла в байтах.
     * @var int    $timeout    Лимит на выполнение cURL в секундах.
     * @var string $useragent  Содержимое заголовка "User-Agent", посылаемого в HTTP-запросе.
     * @var int    $buffersize Размер буфера в байтах.
     * @var bool   $redirects  Разрешить следовать перенаправлениям по заголовку "Location".
     * @var int    $maxredirs  Максимальное количество принимаемых перенаправлений.
     * @var array  $curl       Произвольные опции cURL.
     */
    public $url,
           $verify     = true,
           $cert       = __DIR__ . '/cacert.pem',
           $maxsize,   // null
           $timeout,   // null
           $useragent, // null
           $buffersize = 1024,
           $redirects  = true,
           $maxredirs  = 10,
           $curl       = [];

    /**
     * @var \tmpfile Класс для работы с временным файлом.
     */
    private $tmpfile;

    /**
     * @var resource Дескриптор cURL.
     */
    private $curlHandler;

    /**
     * @var resource Дескриптор временного файла.
     */
    private $fileHandler;

    /**
     * @var int Код ошибки cURL.
     */
    private $errorCode;

    /**
     * @var string Текстовое сообщение ошибки cURL.
     */
    private $errorMessage;

    public function __construct(
        string $url,
        array  $options = []
    ) {
        $this->url = $url;

        foreach ($options as $name => $value) {
            $this->{$name} = $value;
        }

        $this->init();
    }

    public function __set($name, $value)
    {
        throw new \Error(
            sprintf('Опции "%s" не существует в конфигурации класса %s', $name, __CLASS__)
        );
    }

    private function init()
    {
        $this->tmpfile = new \tmpfile;

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

        fclose($this->fileHandler);
        curl_close($this->curlHandler);
    }

    private function prepareOptions(array $options)
    {
        if ($this->verify) {
            $options[is_dir($this->cert) ? CURLOPT_CAPATH : CURLOPT_CAINFO] = $this->cert;
        } else {
            $options += [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ];
        }

        if ($this->maxsize) {
            $options += [
                CURLOPT_NOPROGRESS       => false,
                CURLOPT_BUFFERSIZE       => $this->buffersize,
                CURLOPT_PROGRESSFUNCTION => [$this, 'checkDownloadedBytes'],
            ];
        }

        $this->timeout and $options[CURLOPT_TIMEOUT] = $this->timeout;

        $options[CURLOPT_USERAGENT] = $this->useragent ?? @$_SERVER['HTTP_USER_AGENT'];

        if ($this->redirects) {
            $options += [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => $this->maxredirs,
            ];
        }

        return curl_setopt_array(
            $this->curlHandler,
            $options += $this->curl
        );
    }

    private function checkDownloadedBytes(
        $curlHandler,
        $downloadSize,
        $downloadedBytes,
        $uploadSize,
        $uploadedBytes
    ) {
        if ($downloadedBytes > $this->maxsize) {
            return -1;
        }
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
            return null;
        }

        $errors = [
            /*  1 */ CURLE_UNSUPPORTED_PROTOCOL => 'Ссылка должна начинаться с http:// или https://.',
            /*  6 */ CURLE_COULDNT_RESOLVE_HOST => 'Укажите корректную ссылку на удалённый файл.',
            /* 22 */ CURLE_HTTP_RETURNED_ERROR  => 'По ссылке "%s" файл не найден.',
            /* 42 */ CURLE_ABORTED_BY_CALLBACK  => 'Файл "%s" не должен превышать %d Мбайт.',
        ];

        $message = isset($errors[$this->errorCode])
            ? $errors[$this->errorCode]
            : 'При загрузке удалённого файла "%s" произошла ошибка.';

        return sprintf(
            $message,
            rawurldecode($this->url),
            $this->maxsize / pow(1024, 2)
        );
    }
}
