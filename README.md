pop-storage
===========

[![Build Status](https://github.com/popphp/pop-storage/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-storage/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-storage)](http://cc.popphp.org/pop-storage/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

* [Overview](#overview)
* [Install](#install)
* [Upgrading to 3.0](#upgrading-to-30)
* [Quickstart](#quickstart)
* [Adapters](#adapters)
    - [AWS S3](#aws-s3)
    - [Microsoft Azure](#microsoft-azure)
    - [Local Disk](#local-disk)
* [Error Handling](#error-handling)
* [Working with Files](#working-with-files)
* [Directories](#directories)
* [Helper Methods](#helper-methods)
* [Accessing the Adapter Directly](#accessing-the-adapter-directly)

Overview
--------
`pop-storage` is a storage component that provides interchangeable adapters to easily manage and switch
between different storage resources. Supported storage adapters are:

- AWS S3
- Microsoft Azure
- Local Disk

**NOTE:** The use of enterprise storage solutions like AWS S3 and Microsoft Azure require credentials and
permissions to be created in their respective administration portals. Please refer to the online documentation,
guidelines and polices for whichever storage platform to which you are attempting to connect your application
using this component. Please take care in granting access and assigning permissions to your application instance.
Always follow the recommended security policies and guidelines of your chosen storage platform.

`pop-storage` is a component of the [Pop PHP Framework](https://www.popphp.org/).

[Top](#pop-storage)

Install
-------

Install `pop-storage` using Composer.

    composer require popphp/pop-storage

Or, require it in your composer.json file

    "require": {
        "popphp/pop-storage" : "^3.0.0"
    }

[Top](#pop-storage)

Upgrading to 3.0
----------------

Version 3.0 is a deliberate breaking release that removes silent failures from the storage API.
If you are upgrading from 2.x, three changes need attention. See [Error Handling](#error-handling)
below for the full exception model this introduces; this section covers only what to change in
existing code.

**1. Failures throw exceptions instead of returning `false` or doing nothing.**

Previously, a failed write could silently do nothing and a missing file could quietly return `false`.
Now every operation either succeeds or throws a typed exception. The old per-layer
`Pop\Storage\Adapter\Exception` and `Pop\Storage\Adapter\Azure\Exception` classes have been removed;
catch the new `Pop\Storage\Exception\*` types instead (or `Pop\Storage\Exception` itself as a
catch-all).

**2. `getFileSize()`, `getFileType()`, `getFileMTime()` and `md5File()` no longer return `false`.**

They now return `int`, `string`, `int|string` and `string` respectively. Code that tested the return
value for `false` needs to catch an exception instead:

```php
// 2.x
$size = $storage->getFileSize('test.pdf');
if ($size === false) {
    // not found
}

// 3.0
try {
    $size = $storage->getFileSize('test.pdf');
} catch (Pop\Storage\Exception\FileNotFoundException $e) {
    // not found
}
```

**3. Path traversal is rejected.**

Any path containing a `..` segment now throws `PathTraversalException` rather than being resolved.
This applies to every path-taking method, including the `name` value of an uploaded file array — which
comes directly from `$_FILES` and is attacker-controlled. If any existing code relied on a `..`
segment being silently resolved, it will now throw instead.

[Top](#pop-storage)

Quickstart
----------

A storage object can be created using one of the factories:

```php
use Pop\Storage\Storage;

$storage = Storage::createAzure('ACCOUNT_NAME', 'ACCOUNT_KEY', 'CONTAINER');
```

Then a local file can be uploaded to the storage platform:

```php
$storage->putFile('test.pdf');
```

Or, a remote file can be downloaded from the storage platform, which will return the
file contents to be utilized within the application:

```php
$fileContents = $storage->fetchFile('test.pdf');
```

[Top](#pop-storage)

Adapters
--------

By default, there are 3 available adapters. All of the adapters share the same interface and
are interchangeable. Other adapters can be created, as long as they implement the same
`Pop\Storage\StorageInterface`.

### AWS S3

The Amazon AWS S3 adapter interfaces with AWS S3 and requires the following credentials
and access information to be obtained from the AWS administration console:

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

### Microsoft Azure

The Microsoft Azure adapter interfaces with Microsoft Azure Storage and requires the following credentials
and access information to be obtained from the Azure administration console:

- Account Name
- Account Key
- The Azure container to access (in the format `container`)

**Note:** The container should be configured to have "hierarchical namespace" support turned on for better
support with filenames and folders.

```php
use Pop\Storage\Storage;

$storage = Storage::createAzure('ACCOUNT_NAME', 'ACCOUNT_KEY', 'CONTAINER');
```

### Local Disk

The local disk adapter allows simple management of files and folders on the local disk of the
application using the same interface as the other adapters. This can be useful for local
development and testing, before switching to one of the enterprise adapters for production.

It only needs the main directory to serve as the base location:

```php
use Pop\Storage\Storage;

$storage = Storage::createLocal(__DIR__ . '/tmp/');
```

[Top](#pop-storage)

Error Handling
--------------

An operation on `pop-storage` either succeeds or throws a typed exception — there are no `false`
return values or silent no-ops to check for. Every exception lives under `Pop\Storage\Exception\*`
and extends `Pop\Storage\Exception`, so a single catch-all always works, with specific types
available when you need to branch on the failure:

```php
try {
    $contents = $storage->fetchFile('test.pdf');
} catch (Pop\Storage\Exception\FileNotFoundException $e) {
    // Handle the missing file specifically
} catch (Pop\Storage\Exception $e) {
    // Or catch any storage failure
}
```

The available exception types are `FileNotFoundException`, `DirectoryNotFoundException`,
`UnableToWriteFileException`, `UnableToReadFileException`, `UnableToDeleteFileException`,
`UnableToCopyFileException`, `UnableToMoveFileException`, `UnableToCreateDirectoryException`,
`UnableToDeleteDirectoryException`, `UnableToGenerateTemporaryUrlException`,
`UnsupportedOperationException` and `PathTraversalException`.

A few methods are deliberate exceptions to the "always throw" rule:

- **`fileExists()`, `isDir()` and `isFile()` always return `bool`.** They're questions, not
  operations, so a path that simply isn't there is a normal `false`, not an error. (They can still
  throw `PathTraversalException` — see below — since that signals invalid input, not a negative
  answer.)
- **`getFileSize()`, `getFileType()`, `getFileMTime()` and `md5File()` never return `false`.** They
  return `int`, `string`, `int|string` and `string` respectively. A missing file throws
  `FileNotFoundException`; metadata that can't be read from an otherwise successful response throws
  `UnableToReadFileException`.

**Path traversal is rejected.** Any path containing a `..` segment throws `PathTraversalException`
rather than being resolved, on every path-taking method across every adapter — including the `name`
value of an uploaded file array, which comes directly from `$_FILES` and is attacker-controlled. A
single leading `/`, `\`, `./` or `.\` is normalized away rather than rejected, so ordinary paths are
unaffected.

[Top](#pop-storage)

Working with Files
------------------

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

`putFileContents()` writes the file whether or not it already existed. If you specifically want to
replace the contents of a file that must already be there — and get a `FileNotFoundException` if it
isn't — use `replaceFileContents()` instead:

```php
$storage->replaceFileContents('test.pdf', $newFileContents);
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

### Streaming files

For large files, the streaming methods move contents through a PHP stream resource instead of
buffering the whole file in memory. `putFileStream()` writes from a readable resource:

```php
$resource = fopen('/path/to/large-video.mp4', 'r');
$storage->putFileStream('large-video.mp4', $resource);
fclose($resource);
```

And `fetchFileStream()` returns a readable resource, which the caller is responsible for closing:

```php
$resource = $storage->fetchFileStream('large-video.mp4');
while (!feof($resource)) {
    echo fread($resource, 8192);
}
fclose($resource);
```

### Temporary (presigned) URLs

This method returns a time-limited URL that grants read access to a file without exposing your
credentials or making the file public — an S3 presigned URL, or an Azure SAS-token URL:

```php
// Valid for 15 minutes (900 seconds) by default
$url = $storage->getTemporaryUrl('test.pdf');
```

```php
// Or, set the expiration explicitly
$url = $storage->getTemporaryUrl('test.pdf', 3600);
```

The local disk adapter has no equivalent concept and throws
`Pop\Storage\Exception\UnsupportedOperationException`.

### Upload files from a server request ($_FILES format)

```php
$storage->uploadFiles($_FILES);
```
```php
// Where $file follows the $_FILES array format specified in PHP:
// $file = ['tmp_name' => '/tmp/Hs87jdk', 'name' => 'test.pdf', 'size' => 8574, 'error' => 0]
$storage->uploadFile($file);
```

### List Files

You can list or search the files in the current location:

```php
$files = $storage->listFiles();
```

```php
$files = $storage->listFiles('test*');
```

```php
$files = $storage->listFiles('*.pdf');
```

List all or search all directories and files together:

```php
$all = $storage->listAll();
```

All three listing methods accept a second `$recursive` parameter (default `false`, which lists only the
current location, one level deep). Passing `true` walks every level below the current location:

```php
// ['test.pdf', 'foo/test2.pdf', 'foo/bar/test3.pdf']
$files = $storage->listFiles(null, true);
```

```php
$dirs = $storage->listDirs(null, true);
$all  = $storage->listAll(null, true);
```

Recursive results are paths relative to the current location, including their intermediate directories,
so each one can be passed straight back into `fetchFile()`, `deleteFile()`, and friends:

```php
foreach ($storage->listFiles(null, true) as $file) {
    $contents = $storage->fetchFile($file);
}
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
$storage->moveFileFromExternal('s3://other-bucket/test.pdf', 'test.pdf');

// Azure example. The source file no longer exists
$storage->moveFileFromExternal('/other-container/test.pdf', 'test.pdf');
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

### Base directory vs. current directory

`setBaseDir()`/`getBaseDir()` control the fixed root the storage object operates against (the bucket
or container itself); `getCurrentDir()` reports wherever `chdir()` last pointed it:

```php
$storage->setBaseDir('s3://my-bucket');
$storage->getBaseDir();    // 's3://my-bucket'
$storage->chdir('foo');
$storage->getCurrentDir(); // 's3://my-bucket/foo'
```

`chdir()` always resolves from the base directory, not from wherever you last `chdir`'d — so it is
not cumulative. Calling `chdir('foo')` and then `chdir('bar')` points at `foo`'s sibling `bar`, not
at `foo/bar`. Call `chdir()` with no argument to return to the base directory.

### List Directories

You can list or search the directories in the current location:

```php
$dirs = $storage->listDirs();
```

```php
$dirs = $storage->listDirs('foo*');
```

```php
$dirs = $storage->listDirs('*foo/');
```

List all or search all directories and files together:

```php
$all = $storage->listAll();
```

And, as with the file listings, a second `$recursive` parameter walks every level below the current location:

```php
$dirs = $storage->listDirs(null, true);
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

`fileExists()`, `isDir()` and `isFile()` always return a `bool`. The four metadata methods below them
never return `false` — if the file isn't there they throw `Pop\Storage\Exception\FileNotFoundException`,
and if the metadata can't be read they throw `Pop\Storage\Exception\UnableToReadFileException`.

[Top](#pop-storage)

Accessing the Adapter Directly
-------------------------------

`Storage` delegates every method it exposes to whichever adapter it was created with, always
matching the shared `Pop\Storage\StorageInterface` contract. A handful of capabilities are specific
to one storage platform and only exist on that adapter's own class, not on the shared interface —
reach them with `getAdapter()` (or its shorter alias, `adapter()`):

```php
$adapter = $storage->adapter(); // same as $storage->getAdapter()
```

The Microsoft Azure adapter is currently the only one with extras beyond the interface, all related
to Azure-specific blob behavior:

```php
// fetchFile()'s second argument: pass false to get the raw Pop\Http\Client\Response back
// instead of the file contents as a string (useful for inspecting response headers).
$response = $storage->adapter()->fetchFile('test.pdf', false);

// deleteFile() and moveFileFromExternal() take an optional trailing $snapshots argument
// controlling Azure's x-ms-delete-snapshots header when the blob has snapshots:
// 'include' (default) deletes the blob and its snapshots, 'only' deletes just the
// snapshots and leaves the blob, and null omits the header entirely.
$storage->adapter()->deleteFile('test.pdf', 'only');
$storage->adapter()->moveFileFromExternal('/other-container/test.pdf', 'test.pdf', 'only');
```

[Top](#pop-storage)

