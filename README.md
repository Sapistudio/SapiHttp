# ChromeClient(fork from spatie/browsershot) -  using headless Chrome

```php
use SapiStudio\Http\Browser\HeadlessChrome;

HeadlessChrome::url('https://example.com')->save($pathToImage);
```
## Requirements

This package requires node 7.6.0 or higher and the Puppeteer Node library.

On a [Forge](https://forge.laravel.com) provisioned Ubuntu 16.04 server you can install the latest stable version of Chrome like this:

```bash
curl -sL https://deb.nodesource.com/setup_8.x | sudo -E bash -
sudo apt-get install -y nodejs gconf-service libasound2 libatk1.0-0 libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgcc1 libgconf-2-4 libgdk-pixbuf2.0-0 libglib2.0-0 libgtk-3-0 libnspr4 libpango-1.0-0 libpangocairo-1.0-0 libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 ca-certificates fonts-liberation libappindicator1 libnss3 lsb-release xdg-utils wget
sudo npm install --global --unsafe-perm puppeteer
sudo chmod -R o+rx /usr/lib/node_modules/puppeteer/.local-chromium
```


# Get a guzzle 6 object
```php
use SapiStudio\Http\RequestClient;

$browserClient  = RequestClient::make()->getClient()
```

# Parse use agent string
```php
use SapiStudio\Http\DeviceDetector;

DeviceDetector::uAgent($b['uagent'])
```



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
