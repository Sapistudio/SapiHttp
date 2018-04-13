const puppeteer     = require('puppeteer');
const request       = JSON.parse(process.argv[2]);

const callChrome    = async () => {
    let browser;
    let page;
    const requestOptions    = {};
    const returnOptions     = {};
    returnOptions.requests  = [];
    returnOptions.response  = {};
    
    try {
        
        browser = await puppeteer.launch({ args: request.options.args || [] });
        page    = await browser.newPage();
        
        if (request.options && request.options.userAgent) {
            await page.setUserAgent(request.options.userAgent);
        }

        if (request.options && request.options.viewport) {
            await page.setViewport(request.options.viewport);
        }
        
        if (request.options && request.options.networkIdleTimeout) {
            requestOptions.waitUntil = 'networkidle';
            requestOptions.networkIdleTimeout = request.options.networkIdleTimeout;
        }
        if (request.options && (request.options.interceptRequests || request.options.blockRequestTypes)) {
            await page.setRequestInterception(true);
            page.on('request', httpRequest => {
                        returnOptions.requests.push(httpRequest.url());
                        httpRequest.continue();
                    }
                );
        }
        let httpResponse = await page.goto(request.url, requestOptions);
        const htmlCode = await page[request.action](request.options);
        returnOptions.response.status   = httpResponse.status();
        returnOptions.response.url      = request.url;
        if(request.action =='content'){
            returnOptions.response.htmlCode = htmlCode;
        }
        console.log(JSON.stringify(returnOptions));
        await browser.close();
    } catch (exception) {
        if (browser) {
            await browser.close();
        }
        returnOptions.response.error = exception.message;
        console.log(JSON.stringify(returnOptions));
        process.exit(0);
    }
};
callChrome();
