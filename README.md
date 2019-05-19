# osTicket plugin: osTicket-Snipe-IT Integrator

Parses incoming messages for [Asset_tag] or {Serial_Number}, and automatically adds a link
to said asset or serial number in Snipe-IT.

eg:
```
I have a chromebook [588835] and it does not want to turn on
```
Will remove the square brackets and add a link, so that when someone clicks on 588835, it will take them to Snipe-IT's asset page.


Concept taken from: https://github.com/snipe/snipe-it/issues/663



## To Install
Clone or Download master [zip](https://github.com/Techno11/os-ticket-snipe-it/archive/master.zip) and extract into /include/plugins/mentioner and Install and enable as per normal osTicket Plugins

## To configure

Visit the Admin-panel, select Manage => Plugins, choose the Mentioner plugin

- Option to Enable or disable the plugin
- Required: API Key for Snipe-IT. Directions to generate one are [here](https://snipe-it.readme.io/reference#generating-api-tokens)
- Required: Snipe-IT URL, including HTTP(s) and slash (/) at end