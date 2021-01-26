README
CONTACT: support@business-fundamentals.biz

To create your Xero app login to your Xero account, then open a new browser window and go to the following URL:

https://developer.xero.com/myapps

Follow the simple instructions to create your new app. SAVE the App name, client ID and client secret values for entry into your SuiteCRM installation. Your redirect URI should match the following:

[Your Suitecrm installation URL]/index.php?entryPoint=XeroCallBack

Install the module in SuiteCRM

Installation of this module is accomplished via the SuiteCRM Module Loader menu option. After installation a Xero Configuration entry MUST be created based on the values that are generated when you created your Xero App as above. Enter ANY value in the Xero Webhook Key at this point in the installation.

We HIGHLY recommend that at this point of the installation you set the Synch to Xero setting to NO Synch.

After validating your module license and creating your Xero Configuration Settings go to the Actions menu and click on the Connect to Xero link. If this action is successful your SuiteCRM installation is now connected to Xero. PLEASE NOTE: The installation will NOT work if this step is NOT taken. After successfully validating to Xero, go to the Admin -> Repair option in SuiteCRM and run a QRR. After completing this step, scroll to the bottom of the screen and execute ANY SQL that is visible there. This step is very important if you are using the SuitetoXero module to generate/update Invoices to/from Xero. If NOT completed that function will NOT operate as expected.

If you want to synch records FROM Xero to SuiteCRM you need to create a webhook. Return to your Xero developer interface (https://developer.xero.com/myapps) Open up your newly created Xero app to view it's details. Click on the Webhooks link and fill out the page as per your requirements. Enter the following in the Send notifications to field and click on the Save button

[Your Suitecrm installation URL]/index.php?entryPoint=xeroWebhooks

Save the Webhook key from Xero. Return to your SuiteCRM Xero Configuration Settings admin module and enter the Webhook key in the Xero Webhook Key field in Suite, save the changes. In Xero -> Webhooks click on the Send 'Intent to Receive' button. This process may take more than one attempt for the connection to be completed, please be patient with it. Once the webhook connection has been created, your Suite to Xero module is ready for testing/use. You can now test the manual options in the Accounts/Contacts/Invoices modules AND/OR start to implement your Synch settings with Xero by making your required selections in the Xero Synchronisation settings section e.g. Synch to Xero, Synch from Xero, Synch MOST recent records

If there is a problem please get back to me using the email contact above OR post a query on the SuiteCRM Store site. I will respond ASAP.

If you have any problems with the installation, and/or thoughts on improving the functionality of the Xero Configuration module and it's associated add-ons, please don't hesitate to contact us
