pop-storage
===========

[![Build Status](https://github.com/popphp/pop-storage/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-storage/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-storage)](http://cc.popphp.org/pop-storage/)

OVERVIEW
--------
`pop-storage` is a storage component that provides interchangeable adapters to easily manage and switch
between different storage resources. Supported storage adapters are:

- AWS S3
- Microsoft Azure
- Local Disk

`pop-storage` is a component of the [Pop PHP Framework](http://www.popphp.org/).

INSTALL
-------

Install `pop-storage` using Composer.

    composer require popphp/pop-storage

Or, require it in your composer.json file

    "require": {
        "popphp/pop-storage" : "^2.0.0"
    }


BASIC USAGE
-----------

### Setting up the Local adapter

```php
use Pop\Storage\Storage;

$storage = Storage::createLocal(__DIR__ . '/tmp/');
```

### Setting up the S3 adapter

```php
use Pop\Storage\Storage;

$storage = Storage::createS3('AWS_BUCKET', new S3\S3Client([
    'credentials' => [
        'key'    => 'AWS_KEY',
        'secret' => 'AWS_SECRET',
    ],
    'region'  => 'AWS_REGION',
    'version' => 'AWS_VERSION'
]));
```

### Setting up the Azure adapter

```php
use Pop\Storage\Storage;

$storage = Storage::createAzure('ACCOUNT_NAME', 'ACCOUNT_KEY', 'CONTAINER');
```

### Put file on the remote location

```php
$storage->putFile('test.pdf');
```

```php
$storage->putFileContents('test.pdf', $fileContents);
```

### Upload files from a server request ($_FILES)

```php
$storage->uploadFiles($_FILES);
```
```php
// Where $file follows the file array format specified in PHP:
// $file = ['tmp_name' => '/tmp/Hs87jdk', 'name' => 'test.pdf', 'size' => 8574, 'error' => 0]
$storage->uploadFile($file);
```

### Copy or move file from one remote location to another

```php
// The source file remains
$storage->copyFile('test.pdf', 'foo/test2.pdf');
```

```php
// The source file no longer exists
$storage->renameFile('test.pdf', 'foo/test2.pdf');
```

### Delete file

```php
$storage->deleteFile('test.pdf');
```

### Fetch file contents

```php
$storage->fetchFile('test.pdf');
```

### Fetch file info

```php
// Return an array of pertinent file information
$storage->fetchFileInfo('test.pdf');
```

### Check if file exists

```php
if ($storage->fileExists('test.txt')) {
    // File exists
}
```