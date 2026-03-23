# ZIPStore

A PHP library for generating and streaming virtual ZIP archives on-the-fly without consuming disk space. Perfect for constraint environments where storage is limited.

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Important Limitations](#important-limitations)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Basic Usage](#basic-usage)
  - [Advanced Reading](#advanced-reading)
  - [Adding Files with Custom Names](#adding-files-with-custom-names)
  - [Handling Duplicates](#handling-duplicates)
  - [Stream to Output](#stream-to-output)
- [Advanced Features](#advanced-features)
  - [Custom Entry File Handlers](#custom-entry-file-handlers)
  - [Seeking Behavior](#seeking-behavior)
- [Configuration Options](#configuration-options)
- [Architecture](#architecture)
- [Examples](#examples)
  - [Simple File Download Server](#simple-file-download-server)
  - [Backup Logs Files](#backup-logs-files)
  - [ZIP From Database BLOBs](#zip-from-database-blobs)
- [License](#license)

## Overview

**ZipStore** enables you to add files to a virtual ZIP store and then read from it as if it were a native file resource, using familiar `seek()` and `read()` operations. The library generates ZIP file components on-demand while reading file contents live, making it ideal for serving large file downloads with minimal memory and disk overhead.

Call `open()` at any point to capture a snapshot of the store's current entry list into an independent `OpenedStore` — a seekable read view over the ZIP data as it stood at that moment. The `Store` itself remains open: you can keep calling `addFile()` after `open()`, and those additions will not affect already-taken snapshots. Calling `open()` multiple times yields independent `OpenedStore` instances, each with its own cursor, each reflecting the state of the store at the time of that call.

### Key Features

- **On-the-fly ZIP generation** - Components are created during reading, not upfront
- **Minimal storage footprint** - Perfect for resource-constrained environments
- **Native file interface** - Use `seek()` and `read()` just like file streams
- **UTF-8 support** - Proper Unicode filename handling
- **Pluggable file handlers** - Implement custom `ZipStoreEntryFile` for non-local filesystem sources
- **Performance optimized** - Lazy loading and caching of ZIP structures
- **Standards compliant** - Generates valid ZIP files compatible with standard tools
- **Zero cleanup** - No manual resource management needed

## Important Limitations

ZipStore focuses on stream-friendly ZIP generation and **does not support**:

- File compression (deflate, bzip2, etc.)
- Encryption
- Files larger than `3.75 GiB` per entry
- More than 65,535 entries per archive
- Archive file larger than `4 GiB` since there is no support for ZIP64 yet

These limitations are by design to keep the library lightweight and performant. For complex ZIP operations, consider using `php-zip` extension directly.

## Requirements

- PHP **≥ 8.1**

## Installation

Install via Composer:

```bash
composer require ntdash/zip-store
```

## Quick Start

### Basic Usage

```php
use ZipStore\Store;

// Create a store
$store = new Store();

// Add files
$store->addFile("path/to/file.json");
$store->addFiles([
    "path/to/video.webm",
    "path/to/sample.png"
]);

// Snapshot the store's current entry list into an independent OpenedStore.
// The Store remains open — further addFile() calls won't affect this snapshot.
$openedStore = $store->open();

// Read bytes from any position
$offset = 0;
$bytes = 1024 * 1024 * 4; // 4 MiB

$openedStore->seek($offset);
$buffer = $openedStore->read($bytes);

// current offset after seek and read
echo $openedStore->tell();  // 4194304 
```

### Advanced Reading

```php
// Read with specific offset
$buffer = $openedStore->read(length: 1024 * 1024 * 4, offset: 1024);

// Default read size is 512 KiB 
$buffer = $openedStore->read(offset: 2048);

// Check if end of file
if ($openedStore->eof()) {
    echo "Reached end of ZIP file";
}

// Get total ZIP file size
$totalSize = $openedStore->getSize();
```

### Adding Files with Custom Names

```php
$store->addFile(
    "path/to/original-file.txt",
    "custom-name-in-zip.txt"
);
```

### Handling Duplicates

Control how duplicate entry names are handled:

```php
use ZipStore\Store;

// Append numerical suffix to duplicates (default)
$store = new Store(Store::DUP_APPEND_NUM);

// Overwrite previous entry with same name
$store = new Store(Store::DUP_OVERWRITE);

// Throw exception on duplicates
$store = new Store(Store::DUP_FAILED | Store::STRICT);
```

### Stream to Output

Common pattern for sending ZIP to client. `open()` snapshots the store at this point — call `getSize()` on the returned `OpenedStore` to get the exact byte count before streaming begins:

```php
$opened = $store->open(); 

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="archive.zip"');
header('Content-Length: ' . $opened->getSize());

$opened->passthru();
```

## Advanced Features

### Custom Entry File Handlers

By default, ZipStore uses the file system to read file contents. You can provide custom implementations of `ZipStoreEntryFile` to handle non-filesystem sources like database BLOBs, S3 objects, or in-memory streams.

#### Registering Custom File Handler

Set your custom file handler globally before creating entries:

```php
use ZipStore\Entry;
use ZipStore\Store;
use ZipStore\EntryArgument;

// Register your custom entry file class
EntryArgument::setEntryFileClass(CustomEntryFile::class);

// Now when you add entries, they'll use your custom handler
$store = new Store();
$store->addFile('custom-data-identifier');
// Snapshot the current entry list — returns an independent OpenedStore cursor
$opened = $store->open();
```

**Important:** The custom class must implement both `__serialize()` and `__unserialize()` magic methods for proper serialization support.

### Seeking Behavior

The `OpenedStore::seek()` method supports all `fseek()` behaviors:

```php
use ZipStore\OpenedStore;

// SEEK_SET (0): Absolute position from start
$opened->seek(1024, SEEK_SET);

// SEEK_CUR (1): Relative to current position
$opened->seek(512, SEEK_CUR);

// SEEK_END (2): Relative to end of file
$opened->seek(-1024, SEEK_END);
```

## Configuration Options

### Store Constructor Options

```php
// No special options (default)
$store = new Store(Store::NO_EXTRA);

// Append numbers to duplicate names
$store = new Store(Store::DUP_APPEND_NUM);

// Overwrite duplicate names
$store = new Store(Store::DUP_OVERWRITE);

// Strict mode: throw exceptions on invalid entries
$store = new Store(Store::STRICT);

// Combine options
$store = new Store(Store::DUP_APPEND_NUM | Store::STRICT);
```

## Architecture

### Core Components

- **Store** - Main interface for adding files and opening archives. Call `open()` at any point to snapshot the current entry list into an `OpenedStore`; the `Store` remains open for further additions.
- **OpenedStore** - Seekable read view over a point-in-time snapshot of a `Store`. Each call to `Store::open()` returns an independent instance with its own cursor position.
- **Entry** - Represents a single file in the archive
- **LocalHeader** - ZIP local file header structure
- **CentralDirectory** - ZIP central directory (file index)
- **EndOfCentralDirectory** - ZIP End of Central Directory record
- **ZipStoreEntryFile** - Interface for custom file sources

## Examples

### Simple File Download Server

```php
<?php

use ZipStore\Store;

$store = new Store();

// Add multiple files
$store->addFile('/var/www/document.pdf', 'document.pdf');
$store->addFile('/var/www/image.jpg', 'image.jpg');
$store->addFile('/var/www/video.mp4', 'video.mp4');

$opened = $store->open();

// Stream to browser
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="download.zip"');
header('Content-Length: ' . $opened->getSize());
header('Cache-Control: public, must-revalidate');

$opened->passthru();
```

### Backup Logs Files

```php
$store = new Store();
$stream = fopen("backup.zip", "w"); 

foreach (glob('/data/backups/*.log') as $logFile) {
    $store->addFile($logFile);
}

$opened = $store->open();

$opened->writeToStream($stream, resetOffset: true);
```

### ZIP From Database BLOBs

```php
use ZipStore\Entry;
use ZipStore\Store;
use ZipStore\EntryArgument;


// First, define your custom file handler
class CustomEntryFile implements ZipStoreEntryFile {

    public function __construct(private readonly int $id) {
       // extra ops (eg. validation, ...)
    }

    ...

    // a simple implementation of read API required by Entry Component to deliver file content from the database
    public function read(int $offset, int $length): false|string {
        $query = "SELECT substr(data, :offset, :length) as data from files where id = :id";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([ 'id' => $this->id, 'offset' => $offset + 1, 'length' => $length]);

        $result = $stmt->fetch();
        return $result ? $result['data'] : false;
    }

    ...
}

// register your custom file handler
EntryArgument::setEntryFileClass(CustomEntryFile::class);

$store = new Store();

// Add database records as ZIP entries
foreach ($database->query('SELECT id, name FROM files') as $row) {
    $store->addFile($row['id'], sprintf('%s-%d.bin', $row['name'], $row['id']));
}

$opened = $store->open();

// Stream to output
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="database-export.zip"');
header('Content-Length: ' . $opened->getSize());


// equivalent (current internal) of OpenedStore@passthru() 
$opened->writeTo(path: "php://output", resetOffset: true);
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
