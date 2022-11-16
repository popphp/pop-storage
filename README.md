pop-storage
===========

[![Build Status](https://github.com/popphp/pop-storage/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-storage/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-storage)](http://cc.popphp.org/pop-storage/)

OVERVIEW
--------
`pop-storage` is a storage wrapper component that provides interchangeable adapters to easily manage and switch
between different storage resources, such as the local disk or a cloud-based storage platform, like AWS S3.

`pop-storage` is a component of the [Pop PHP Framework](http://www.popphp.org/).

INSTALL
-------

Install `pop-storage` using Composer.

    composer require popphp/pop-storage

BASIC USAGE
-----------

### Setting up the Local adapter

```php

$storage = new Pop\Storage\Local(__DIR__ . '/tmp/');

```

### Setting up the S3 adapter

```php

$storage = new Pop\Storage\S3($_ENV['AWS_BUCKET'], new S3\S3Client([
    'credentials' => [
        'key'    => $_ENV['AWS_KEY'],
        'secret' => $_ENV['AWS_SECRET'],
    ],
    'region'  => $_ENV['AWS_REGION'],
    'version' => $_ENV['AWS_VERSION']
]));

```

### Checking if a file exists

```php

if ($storage->fileExists('test.txt')) {
    // File exists
}

```

### Fetch contents of a file

```php

$fileContents = $storage->fetchFile('test.txt');


```

### Copy a file

```php

$adapter->copyFile('test.txt', 'test2.txt');

```

### Rename a file

```php

$adapter->renameFile('test.txt', 'test1.txt');

```


### Replace a file

```php

$adapter->replaceFile('test1.txt', 'new contents');

```

### Make a directory

```php

$adapter->mkdir('test');

```

### Remove a directory

```php

$adapter->rmdir('test');

```