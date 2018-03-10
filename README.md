# ChromeClient(fork from spatie/browsershot) -  using headless Chrome

```php
use SapiStudio\Http\Browser\HeadlessChrome;

HeadlessChrome::url('https://example.com')->save($pathToImage);
```
# Get a guzzle 6 object
```php
use SapiStudio\Http\RequestClient;

$browserClient  = (new RequestClient())->getClient()
```

# Parse use agent string
```php
use SapiStudio\Http\DeviceDetector;

DeviceDetector::uAgent($b['uagent'])
```



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
