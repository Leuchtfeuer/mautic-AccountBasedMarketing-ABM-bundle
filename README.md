# ABM (Account Based Marketing) by Leuchtfeuer

## Overview

A suite of company-centric enhancements like Company Segments and Company Scoring.

This plugin brings company-based Segments to Mautic and extends Mautic capabilities for working with Companies.

## Requirements for this release

> [!TIP]
> Other releases of this plugin may cover different Mautic versions!

- Mautic 7.2+
- PHP 8.2+

## Installation

### Composer

This plugin can be installed through composer.

### Manual Installation

Alternatively, it can be installed manually, following the usual steps:

- Download the plugin
- Unzip to the Mautic `plugins` directory
- Rename folder to `LeuchtfeuerAccountBasedMarketingAbmBundle`
- In the Mautic backend, go to the `Plugins` page as an administrator
- Click on the `Install/Upgrade Plugins` button to install the Plugin.

OR

- If you have shell access, execute `php bin/console cache:clear` and `php bin/console mautic:plugins:reload` to install the plugins.

## Configuration

1. Go to the `Plugins` page
2. Click on the `ABM (Account Based Marketing) by Leuchtfeuer` plugin
3. ENABLE the plugin

No additional configuration is required after enabling the plugin.

## Usage

The plugin brings a new menu item `Companies -> Company Segments`. Here you can create and manage your Company Segments, and see the number of Companies in each Segment.

Company Segments are populated automatically based on their filters. Filters can be based on Company attributes, Contact attributes, or Contact Segment Membership.

You can filter for Company Segments in the Company list view. Just enter `companysegment:<company segment alias>` in the filter.

In the Company list view, you can also change Company Segment membership for multiple Companies at once via the batch action `Change Company Segments`.

In Campaigns, you now have a new Action called `Modify Company Segments`. It adds or removes the contact's primary Company from Company Segments.

In Campaigns, you now have a new Condition called `Company Segments`. It checks the Company Segments of the contact's primary Company.

In Dynamic Content, you can filter on Company Segment membership.

To update the Company Segments based on their filters, there is a console command to be used as a cron job:

```
php bin/console leuchtfeuer:abm:segments:update
```

It works just like the contact segments update command.

## API support

Standard CRUD endpoints are available for managing Company Segments:

```
GET    /api/companysegments
GET    /api/companysegments/{id}
POST   /api/companysegments/new
PUT    /api/companysegments/{id}/edit
PATCH  /api/companysegments/{id}/edit
DELETE /api/companysegments/{id}/delete
```

Additional endpoints for managing Companies in a Segment:

```
POST /api/companysegments/{id}/company/{companyId}/add
POST /api/companysegments/{id}/companies/add
POST /api/companysegments/{id}/company/{companyId}/remove
```

## Troubleshooting

Make sure you have not only installed but also enabled the Plugin.
If things are still funny, please try:

```
php bin/console cache:clear
```

## Credits

## Author

Leuchtfeuer Digital Marketing GmbH

Please raise any issues in GitHub.
For all other things, please email mautic-plugins@Leuchtfeuer.com

## License

This plugin is licensed under the GPL v3 License.
