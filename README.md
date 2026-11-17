# ABM (Account Based Marketing) by Leuchtfeuer

## Overview

A suite of company-centric enhancements like Company Segments and Company Scoring.

This plugin brings company-based Segments to Mautic and extends Mautic capabilities for working with Companies.

## Requirements for this release

> [!TIP]
> Other releases of this plugin may cover different Mautic versions!

- Mautic 7.3+
- PHP 8.2+
- For the Company `History` tab: Mautic core with [#18106](https://github.com/mautic/mautic/pull/18106) (company view hooks) and [#TBD](https://github.com/mautic/mautic/pull/TBD) (timeline `leadIds` filter). Until they are released, apply them as Composer patches.

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

The Company detail view has a new `History` tab. It shows Company events (created, Company Segment changes, Contacts added or removed, imports) together with the timeline events of all Contacts of the Company. Search, include/exclude filters, sorting and pagination work like in the Contact timeline.

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

## Known issues

The Company `History` tab loads timeline events of all Contacts of the Company with one query set. This works for core, DOI and PropagateBounce events, and for plugins that register their event types (see [Company timeline for plugin developers](#company-timeline-for-plugin-developers)).

> [!WARNING]
> Timeline events of all other plugins are loaded **per Contact**: each Contact triggers its own set of queries, on every page, search and filter change. The query count grows linearly with the number of Contacts. For a Company with 1,000 Contacts this means thousands of queries per request, which can overload the database and make the `History` tab time out.
>
> To avoid it, exclude these event types in the filter, or register them as described below if their queries support the `leadIds` filter.

## Company timeline for plugin developers

If your plugin adds Contact timeline events and its queries support the core `leadIds` filter (for example via `TimelineEventLogTrait`), register the event types so that they are loaded for all Contacts at once:

```php
use Symfony\Contracts\EventDispatcher\Event;

private const ABM_LEAD_IDS_TYPES_EVENT = 'MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineLeadIdsTypesEvent';

public static function getSubscribedEvents(): array
{
    return [
        self::ABM_LEAD_IDS_TYPES_EVENT => 'onCompanyTimelineLeadIdsTypes',
    ];
}

public function onCompanyTimelineLeadIdsTypes(Event $event): void
{
    if (method_exists($event, 'addType')) {
        $event->addType('myplugin.event_type');
    }
}
```

The event name is a plain string, so your plugin does not depend on this plugin: without it, the event is never dispatched and the listener never runs.

> [!WARNING]
> Only register types whose queries apply the `leadIds` filter. Otherwise the Company timeline shows events of all Contacts in the instance.

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
