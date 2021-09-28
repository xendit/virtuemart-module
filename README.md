# Virtuemart 3 - Xendit Plugin

## How to Install

## Prerequisite
- Install [Joomla](https://devilbox.readthedocs.io/en/latest/examples/setup-joomla.html) via Devilbox
- Install [VirtueMart](https://virtuemart.net/downloads) extension or download from Joomla marketplace

### Manually
To install this plugin manually, there are two main steps that you need to do:

#### Download and upload
1. Download [the latest release](https://github.com/xendit/virtuemart-module/releases/tag/1.2.0).
2. On your store's administrator page, go to `Extensions -> Install` page
3. Select `Upload Package File`
4. Upload the downloaded file from step 1
5. You should get a message saying that your installation is successful

#### Activate
1. You should see `VM Payment - Xendit` in the list of extension
2. Select `VM Payment - Xendit` and click `Install` button
3. Go to `Extensions -> Manage` page
4. Type `xendit` in the search bar
5. Select `VM Payment - Xendit` and click `Enable` button

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
