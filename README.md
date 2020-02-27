# Virtuemart 3 - Xendit Plugin

## How to Install

### Manually
To install this plugin manually, there are two main steps that you need to do:
#### Download and upload
1. Download the source code of this plugin.
2. Change the root folder of this source code to `xendit`. The desired folder structure should look like this:
```
|xendit
    |language
        |..
    |xendit
        |..
    |README.md
    |xendit.php
    |xendit.xml
```
3. Using an FTP or any other means, upload the `xendit` folder to your server's VM Payment plugin folder. It should be in this directory: `ROOT_JOOMLA_DIR/plugins/vmpayment`.

#### Activate
1. On your store's administrator page, go to `Extensions -> Discover` page
2. Click the `Discover` button
3. You should see `VM Payment - Xendit` in the list of extension
4. Select `VM Payment - Xendit` and click `Install` button
5. Go to `Extensions -> Manage` page
6. Type `xendit` in the search bar
7. Select `VM Payment - Xendit` and click `Enable` button

## How to Use
### Create new Xendit payment method
1. On your store's administrator page, go to `Components -> VirtueMart -> Payment Method` page
2. Click the `New` button
3. Fill the form:
- Payment Name -> This will be shown to your customer in the checkout page. E.g. `Xendit`
- Description -> This will be shown to your customer in the checkout page. E.g. `Pay via Xendit`
- Payment Method -> `VM Payment - Xendit`
- Currency -> `Indonesian Rupiah`. Currently we only support IDR as the currency

## Ownership

Team: [TPI Team](https://www.draw.io/?state=%7B%22ids%22:%5B%221Vk1zqYgX2YqjJYieQ6qDPh0PhB2yAd0j%22%5D,%22action%22:%22open%22,%22userId%22:%22104938211257040552218%22%7D)

Slack Channel: [#p-integration](https://xendit.slack.com/messages/p-integration)

Slack Mentions: `@troops-tpi`
