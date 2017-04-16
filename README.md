# UploadedUrl

Класс для загрузки удалённых файлов на сервер.

```php
<?php

require 'vendor/autoload.php';

use Denisyuk\{UploadedUrl, UrlException};

try {
    $file = new UploadedUrl('http://i.imgur.com/JRorA8V.gif', [
    
        // default
        'verify'     => true,
        'cert'       => 'cacert.pem',
        'maxsize'    => null,
        'timeout'    => null,
        'buffersize' => 1024,
        'redirects'  => true,
        'maxredirs'  => 10,
        'curl'       => [],
    ]);
    
    if (!$file->isValid()) {
        throw new UrlException(
            $file->getErrorMessage(),
            $file->getErrorCode()
        );
        
        // Здесь мы можем сформировать свои названия
        // ошибок исходя из getErrorCode(), т. к. это
        // код ошибки cURL. Например:
        //
        // $code = $file->getErrorCode();
        // $errors = [
        //     /*  1 */ CURLE_UNSUPPORTED_PROTOCOL => '',
        //     /*  6 */ CURLE_COULDNT_RESOLVE_HOST => '',
        //     /* 22 */ CURLE_HTTP_RETURNED_ERROR  => '',
        //     /* 42 */ CURLE_ABORTED_BY_CALLBACK  => '',
        // ];
        // $message = isset($errors[$code]) ? $errors[$code] : '';
        //
        // throw new UrlException($message, $code);
    }
    
    echo $file->getMimeType();
    
    $file->move(__DIR__ . '/files', 'custom-name.jpg');
} catch (UrlException $e) {
     /* ... */
}
```

[![Favicon](https://hsto.org/files/e9b/a97/31d/e9ba9731d607484cb3abfdd51fd494d5.png)](https://denisyuk.by) [Александр Денисюк](https://denisyuk.by), 2017
