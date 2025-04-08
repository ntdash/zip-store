# ZIP STORE

In a constraint environment where a storage is scarce, ZipStore allow us to load file into a a virtual store and to seek and read any bytes from any postion in the file as if you were seeking and reading a native resource (i.e: returned value of fopen())

ZipStore reduce the storage usage by only genrating the components of a the resulting zip file while reading the content of added files on the fly

## Important

ZipStore, Even though convinient, does restrict you from delivering core features of a zip file, like encryption and content deflation, so it is to be use with the awarness of the limitation


## Installation

* php **>=8.1** required

```sh
composer require ntdash/zip-store
```

## Usage

### Initialization

```php
/* Typical initialization */
$store = new \ZipStore\Store();
```

### Add file(s)

```php
$store->addFile("map.json");

$store->addFiles([
    "video.webm",
    "sample.png"
]);
```

### Ready to start reading

```php
$openedStore = $store->open();
```

### Seek and Read

```php
/* offset retrieve from custom user logic or Request Range*/
$offset = ...; 
$bytes = 1024 * 1024 * 4;

$openStore->seek($offset);

$buff = $openStore->read($bytes);

/* both at the same time */
$buff2 = $openedStore->read($bytes, $offset);

/* number of bytes to be read is optionnal with a default of 1MiB */
$buff3 = $openedStore->read(offset: $offset);
```

### Closing resources

yeah! you don't ! 'cause there is nothing to close