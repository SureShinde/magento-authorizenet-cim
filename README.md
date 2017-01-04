#ABOUT

This module allows you to accept payments using[Authorize.Net CIM](http://www.authorize.net/solutions/merchantsolutions/merchantservices/cim/) payment API. This extension are not saving any sensitive information inside of your database, only last 4 digits of Credit Card number and expiration dates, everything else will be managed by Authorize.Net side.

You can obtain sandbox account[here](https://developer.authorize.net/hello_world/sandbox/)

Current version is 0.9alpha. Any feedback and contribution are welcomed.

**At this moment following features are implemented:**

* Auth/Capture of funds by guest customer using credit card(no CIM profiles will be created);
* Auth/Capture of funds by registered customer using credit card(CIM profile will be created and authorized/captured);
* Auth/Capture of funds by registered customer using saved payment profile;
* Online Refund/Credit Memo creation for all orders being purchased using this extension;
* Possibility for registered customers to add/view/edit/delete their credit cards;
* Possibility for admins to view list of customer's credit cards on Customer page;
* Possibility for admins to view whole list of saved credit cards under Customers -> Manage Credit Cards;
* Possibility for admins to edit/delete existing credit card;
* Possibility for admins to add new credit card selecting specific customer to add.

#Installation

To install this extension you need to execute following commands:
* modman init
* modman clone git@github.com:thecvsi/magento-authorizenet-cim.git

To update extension to latest version you need to run following command:

* modman update magento-authorizenet-cim

Please, refer to [modman](https://github.com/colinmollenhour/modman) documentation about installation.

**Magento Support:**

* [Homepage](http://magento.com) (v.1.9.x)
* [Documentation](http://docs.magentocommerce.com)

