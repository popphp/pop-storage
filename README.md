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

### Setting up the Local adapter

```php
use Pop\Storage\Storage;

$storage = Storage::createLocal(__DIR__ . '/tmp/');
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

### Copy of move file from/to an external location on the same remote storage resource

This allows you to copy or move files between different AWS buckets or Azure containers.

**To External**

```php
// AWS example. The source file remains
$storage->copyFileToExternal('test.pdf', 's3://other-bucket/test.pdf');

// Azure example. The source file remains
$storage->copyFileToExternal('test.pdf', '/other-bucket/test.pdf');
```

```php
// AWS example. The source file no longer exists
$storage->moveFileToExternal('test.pdf', 's3://other-bucket/test.pdf');

// Azure example. The source file no longer exists
$storage->moveFileToExternal('test.pdf', '/other-bucket/test.pdf');
```

**From External**

```php
// AWS example. The source file remains
$storage->copyFileFromExternal('s3://other-bucket/test.pdf', 'test.pdf');

// Azure example. The source file remains
$storage->copyFileFromExternal('/other-bucket/test.pdf', 'test.pdf');
```

```php
// AWS example. The source file no longer exists
$storage->moveFileToExternal('s3://other-bucket/test.pdf', 'test.pdf');

// Azure example. The source file no longer exists
$storage->moveFileToExternal('/other-bucket/test.pdf', 'test.pdf');
```


### Delete file

```php
$storage->deleteFile('test.pdf');
```

### Fetch file contents

```php
$fileContents = $storage->fetchFile('test.pdf');
```

### Fetch file info

```php
// Return an array of pertinent file information
$info = $storage->fetchFileInfo('test.pdf');
```

### Check if file exists

```php
if ($storage->fileExists('test.pdf')) {
    // File exists
}
```

### Managing directories

The AWS and Azure storage resources don't explicitly support "directories" or "folders." However, they
do still allow for a "directory-like" structure in the form of "prefixes." The `pop-storage` normalizes
that functionality into a more "directory-like" interface that allows the ability to change directories,
make directories and remove directories.

**NOTE:** The Azure storage resource doesn't allow the explicit creation or removal of empty directories.
Instead, a new "directory" (prefix) is created automatically created with an upload file that utilizes a prefix.
Conversely, a "directory" (prefix) is automatically removed when the last file that utilizes the prefix is deleted.

```php
$storage = Storage::createS3('s3://my-bucket', new S3\S3Client([
    'credentials' => [
        'key'    => 'AWS_KEY',
        'secret' => 'AWS_SECRET',
    ],
    'region'  => 'AWS_REGION',
    'version' => 'AWS_VERSION'
]));

// Create the bucket 's3://my-bucket/foo'
$storage->mkdir('foo');

// Point the adapter at 's3://my-bucket/foo' Any files pushed will store here.
$storage->chdir('foo');

// Removes the bucket and its content
$storage->rmdir('foo');

```

### Various helper methods

```php

var_dump($storage->isDir('foo'));             // Returns bool
var_dump($storage->isFile('test.pdf'));       // Returns bool
var_dump($storage->getFileSize('test.pdf'));  // Returns filesize value as an integer
var_dump($storage->getFileType('test.pdf'));  // Return either 'file' or 'dir'
var_dump($storage->getFileMTime('test.pdf')); // Returns date/time value
var_dump($storage->md5File('test.pdf'));      // Returns MD5 hash of file
```