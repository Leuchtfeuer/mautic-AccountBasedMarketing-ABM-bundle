Mautic.companyTimelineOnLoad = function (container, response) {
    Mautic.leadTimelineOnLoad(container, response);
};

mQuery(document).on('mautic:onPageLoad:after', function (event, container) {
    var timelineForm = mQuery(container).find('#timeline-filters[data-company-timeline]');
    if (!timelineForm.length) {
        return;
    }

    timelineForm.on('change', function () {
        timelineForm.submit();
    }).on('keyup', function () {
        timelineForm.delay(200).submit();
    }).on('submit', function (e) {
        e.preventDefault();
        Mautic.refreshLeadTimeline(timelineForm);
    });

    Mautic.loadAndProcessPageContent('#timeline-table');
});
