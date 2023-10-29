pop-storage
===========

[![Build Status](https://github.com/popphp/pop-storage/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-storage/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-storage)](http://cc.popphp.org/pop-storage/)

[![Join the chat at https://discord.gg/D9JBxPa5](https://www.popphp.org/assets/img/discord-chat.svg)](https://discord.gg/D9JBxPa5)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [Adapters](#adapters)
* [Files](#files)
* [Directories](#directories)
* [Helper Methods](#helper-methods)

Overview
--------
`pop-storage` is a storage component that provides interchangeable adapters to easily manage and switch
between different storage resources. Supported storage adapters are:

- AWS S3
- Microsoft Azure
- Local Disk

**NOTE:** The use of enterprise storage solutions like AWS S3 and Microsoft Azure require credentials to
be created in their respective administration portals. Please refer to the online documentation, guidelines
and polices for whichever storage platform to which you are attempting to connect your application using this
component. Please take care in granting access and assigning permissions to your application instance. Always
follow the recommended security policies and guidelines of your chosen storage platform.

`pop-storage` is a component of the [Pop PHP Framework](http://www.popphp.org/).

[Top](#pop-storage)

Install
-------

Install `pop-storage` using Composer.

    composer require popphp/pop-storage

Or, require it in your composer.json file

    "require": {
        "popphp/pop-storage" : "^2.0.0"
    }

[Top](#pop-storage)

Quickstart
----------

A storage object can be created using one of the factories:

```php
```php
use Pop\Storage\Storage;

$storage = Storage::createAzure('ACCOUNT_NAME', 'ACCOUNT_KEY', 'CONTAINER');
```

Then a local file can be uploaded to the storage platform:

```php
$storage->putFile('test.pdf');
```

Or, a remove file can be downloaded from the storage platform, which will return the
file contents to be utilized within the application:

```php
$fileContents = $storage->fetchFile('test.pdf');
```

[Top](#pop-storage)

Adapters
--------

All of the adapters share the same interface and are interchangeable.

### Setting up the Azure adapter

The Azure adapter interfaces with Microsoft Azure Storage and requires the following credentials
and access information to be obtained from the AWS administration console:

- Account Name
- Account Key
- The Azure container to access (in the format `container`)

```php
use Pop\Storage\Storage;

$storage = Storage::createAzure('ACCOUNT_NAME', 'ACCOUNT_KEY', 'CONTAINER');
```

### Setting up the S3 adapter

The S3 adapter interfaces with AWS S3 and requires the following credentials and access
information to be obtained from the AWS administration console:

- AWS Key
- AWS Secret
- AWS Region
- AWS Version (usually `latest`)
- The AWS S3 bucket to access (in the format `s3://bucket`)

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

### Setting up the Local adapter

The local adapter allows simply management of files and folders to the local disk of the
application using the same interface as the other adapters. This can be useful for local
development and testing, before switching to one of the enterprise adapters for production.

It only needs the main directory to serve as the base location:

```php
use Pop\Storage\Storage;

$storage = Storage::createLocal(__DIR__ . '/tmp/');
```

[Top](#pop-storage)

Files
-----

There are a number of available methods to assist in the uploading and downloading of files
to and from the storage platform, as well as obtaining general data and information about them. 

### Put a local file on the remote location

Use a file on disk:

```php
$storage->putFile('test.pdf');
```

Use a stream of file contents:

```php
$storage->putFileContents('test.pdf', $fileContents);
```

### Fetch file contents

This method returns the file contents to be utilized within the application:

```php
$fileContents = $storage->fetchFile('test.pdf');
```

### Fetch file info

This method uses a custom request (i.e, a `HEAD` request) to return general information
about a file without downloading the file's contents:

```php
// Returns an array of file info:
$info = $storage->fetchFileInfo('test.pdf');
```

### Upload files from a server request ($_FILES format)

```php
$storage->uploadFiles($_FILES);
```
```php
// Where $file follows the $_FILES array format specified in PHP:
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

This allows you to copy or move files between different AWS buckets or Azure containers
that are outside the currently referenced bucket or container.

**To External**

```php
// AWS example. The source file remains
$storage->copyFileToExternal('test.pdf', 's3://other-bucket/test.pdf');

// Azure example. The source file remains
$storage->copyFileToExternal('test.pdf', '/other-container/test.pdf');
```

```php
// AWS example. The source file no longer exists
$storage->moveFileToExternal('test.pdf', 's3://other-bucket/test.pdf');

// Azure example. The source file no longer exists
$storage->moveFileToExternal('test.pdf', '/other-container/test.pdf');
```

**From External**

```php
// AWS example. The source file remains
$storage->copyFileFromExternal('s3://other-bucket/test.pdf', 'test.pdf');

// Azure example. The source file remains
$storage->copyFileFromExternal('/other-container/test.pdf', 'test.pdf');
```

```php
// AWS example. The source file no longer exists
$storage->moveFileToExternal('s3://other-bucket/test.pdf', 'test.pdf');

// Azure example. The source file no longer exists
$storage->moveFileToExternal('/other-container/test.pdf', 'test.pdf');
```

### Delete file

```php
$storage->deleteFile('test.pdf');
```

[Top](#pop-storage)

Directories
-----------

The AWS and Azure storage resources don't explicitly support "directories" or "folders." However, they
do still allow for a "directory-like" structure in the form of "prefixes." The `pop-storage` component
normalizes that functionality into a more "directory-like" interface that allows the ability to change
directories, make directories and remove directories.

**NOTE:** The creation or removal of empty directories is only allowed with the S3 and local adapters.
The Azure storage resource doesn't allow the explicit creation or removal of empty directories. Instead,
a new "directory" (prefix) is created automatically created with an uploaded file that utilizes a prefix.
Conversely, a "directory" (prefix) is automatically removed when the last file that utilizes the prefix
is deleted.

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

// Point the adapter at 's3://my-bucket/foo'
// Any files pushed will store here
// Any delete calls will delete files from here
$storage->chdir('foo');

// Removes the bucket and its content
$storage->rmdir('foo');
```

[Top](#pop-storage)

Helper Methods
--------------

There are a number of helper methods to provide information on file status or things like
whether or not the file exists.

```php
var_dump($storage->fileExists('test.pdf'))    // Returns bool
var_dump($storage->isDir('foo'));             // Returns bool
var_dump($storage->isFile('test.pdf'));       // Returns bool
var_dump($storage->getFileSize('test.pdf'));  // Returns filesize value as an integer
var_dump($storage->getFileType('test.pdf'));  // Return either 'file' or 'dir'
var_dump($storage->getFileMTime('test.pdf')); // Returns date/time value
var_dump($storage->md5File('test.pdf'));      // Returns MD5 hash of file
```

[Top](#pop-storage)

